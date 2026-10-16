<?php

use PHPUnit\Framework\TestCase;

/** The photo a person can put beside their name in the panel's header (Minha conta). */
final class AvatarTest extends TestCase
{
    private int $user;
    private string $uploads;
    private string $tmp;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->uploads = sys_get_temp_dir() . '/pb-avatar-' . bin2hex(random_bytes(4));
        mkdir($this->uploads, 0777, true);
        $GLOBALS['pb_config'] = pb_test_config(['uploads_dir' => $this->uploads]);
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->user = pb_create_user('Ana Souza', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->tmp = sys_get_temp_dir() . '/pb-avatar-src-' . bin2hex(random_bytes(4));
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        $_SESSION = $_GET = $_POST = [];
        pb_rmtree($this->uploads);
        pb_rmtree($this->tmp);
    }

    /** A wide picture: a red band on the left, blue everywhere else. Returns the file. */
    private function picture(string $name = 'foto.png', int $width = 400, int $height = 300): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 0, 0, 40, $height, imagecolorallocate($image, 255, 0, 0));
        $file = "$this->tmp/$name";
        imagepng($image, $file);
        return $file;
    }

    private function photo(int $id): string
    {
        return (string) (pb_find_user($id)['avatar'] ?? '');
    }

    public function test_a_person_has_no_photo_until_they_send_one(): void
    {
        $this->assertSame('', pb_avatar_url(pb_find_user($this->user)));
    }

    public function test_the_photo_becomes_a_centred_square_of_a_fixed_size(): void
    {
        pb_avatar_store($this->user, $this->picture());
        $path = $this->photo($this->user);

        $this->assertMatchesRegularExpression('~^avatars/' . $this->user . '-[0-9a-f]{12}\.(webp|png)$~', $path, 'a random name, nothing from the person');
        $this->assertFileExists("$this->uploads/$path");
        [$width, $height] = getimagesize("$this->uploads/$path");
        $this->assertSame([PB_AVATAR_SIZE, PB_AVATAR_SIZE], [$width, $height]);
        $image = imagecreatefromstring((string) file_get_contents("$this->uploads/$path"));
        $left = imagecolorsforindex($image, imagecolorat($image, 2, 80));
        $this->assertGreaterThan($left['red'], $left['blue'], 'the red band on the left was cut off by the centred crop');
        $this->assertSame(pb_url('content/uploads/' . $path), pb_avatar_url(pb_find_user($this->user)));
    }

    /** @return array<string, array{string}> */
    public static function formats(): array
    {
        return ['png' => ['png'], 'jpeg' => ['jpeg'], 'webp' => ['webp'], 'gif' => ['gif']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formats')]
    public function test_every_accepted_type_comes_out_opaque_and_of_the_same_colours(string $type): void
    {
        $image = imagecreatetruecolor(300, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        $file = "$this->tmp/f.$type";
        match ($type) {
            'png' => imagepng($image, $file),
            'jpeg' => imagejpeg($image, $file, 95),
            'webp' => imagewebp($image, $file, 95),
            'gif' => imagegif($image, $file),
        };
        pb_avatar_store($this->user, $file);
        $out = imagecreatefromstring((string) file_get_contents("$this->uploads/" . $this->photo($this->user)));
        $pixel = imagecolorsforindex($out, imagecolorat($out, 80, 80));
        $this->assertSame(0, $pixel['alpha'], "$type: opaque");
        $this->assertGreaterThan(200, $pixel['red'], "$type: red");
        $this->assertLessThan(60, $pixel['blue'], "$type: not blue");
    }

    public function test_sending_another_photo_deletes_the_first(): void
    {
        pb_avatar_store($this->user, $this->picture());
        $first = $this->photo($this->user);
        pb_avatar_store($this->user, $this->picture('outra.png', 200, 200));
        $second = $this->photo($this->user);

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist("$this->uploads/$first");
        $this->assertFileExists("$this->uploads/$second");
    }

    public function test_removing_the_photo_brings_the_initials_back(): void
    {
        pb_avatar_store($this->user, $this->picture());
        $path = $this->photo($this->user);
        pb_avatar_remove($this->user);

        $this->assertSame('', $this->photo($this->user));
        $this->assertFileDoesNotExist("$this->uploads/$path");
        pb_avatar_remove($this->user); // nothing there: nothing happens
    }

    public function test_only_pictures_are_accepted_by_what_is_inside_the_file_not_by_its_name(): void
    {
        file_put_contents("$this->tmp/falsa.jpg", "<?php echo 'not a picture';");
        try {
            pb_avatar_store($this->user, "$this->tmp/falsa.jpg");
            $this->fail('a script named .jpg was accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Tipo de arquivo não aceito', $e->getMessage());
        }
        $this->assertSame('', $this->photo($this->user));

        file_put_contents("$this->tmp/grande.png", str_repeat('x', PB_AVATAR_MAX_BYTES + 1));
        $this->expectExceptionMessage('passa de 5 MB');
        pb_avatar_store($this->user, "$this->tmp/grande.png");
    }

    public function test_a_file_that_was_not_uploaded_through_the_form_is_refused(): void
    {
        $this->expectExceptionMessage('Escolha uma foto');
        pb_avatar_set($this->user, []);
    }

    public function test_only_a_path_of_the_shape_the_code_writes_is_ever_deleted(): void
    {
        file_put_contents("$this->uploads/important.txt", 'keep');
        mkdir("$this->uploads/avatars");
        file_put_contents("$this->uploads/avatars/1-notahexname.webp", 'keep');
        foreach (['../important.txt', 'important.txt', 'avatars/../important.txt', 'avatars/1-notahexname.webp', ''] as $path) {
            pb_avatar_forget($path);
        }
        $this->assertFileExists("$this->uploads/important.txt");
        $this->assertFileExists("$this->uploads/avatars/1-notahexname.webp");
    }

    public function test_deleting_a_person_deletes_their_photo(): void
    {
        $other = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_avatar_store($other, $this->picture());
        $path = $this->photo($other);
        pb_delete_user($other, $this->user);
        $this->assertFileDoesNotExist("$this->uploads/$path");
    }

    private function screen(string $path): string
    {
        $_SESSION['user_id'] = $this->user;
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_admin('GET', $path);
        return ob_get_clean();
    }

    public function test_the_header_and_the_account_screen_show_the_photo_or_the_initials(): void
    {
        $without = $this->screen('/admin/account');
        $this->assertStringContainsString('<span class="avatar" aria-hidden="true" data-initials="AS"></span>', $without);
        $this->assertStringContainsString('class="avatar big"', $without);
        $this->assertStringContainsString('action="/admin/account/avatar"', $without);
        $this->assertStringNotContainsString('Remover foto', $without, 'nothing to remove');

        pb_avatar_store($this->user, $this->picture());
        $with = $this->screen('/admin/account');
        $url = htmlspecialchars(pb_avatar_url(pb_find_user($this->user)), ENT_QUOTES);
        $this->assertStringContainsString('<img class="avatar" src="' . $url . '"', $with, 'the header');
        $this->assertStringContainsString('<img class="avatar big" src="' . $url . '"', $with, 'and the screen');
        $this->assertStringNotContainsString('data-initials="AS"', $with);
        $this->assertStringContainsString('Remover foto', $with);
    }
}
