<?php

use PHPUnit\Framework\TestCase;

/** The browser cache for the site's files (a few lines in .htaccess, put there and taken out by Settings → Address and languages) and the smaller copies of the pictures. */
final class SpeedTest extends TestCase
{
    private const ORIGINAL = "# PageBrick: every request goes through index.php.\nOptions -Indexes\n\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule ^core/(?!assets/) - [F,L]\n</IfModule>\n";

    private string $dir;
    private int $admin;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->dir = sys_get_temp_dir() . '/pb-speed-' . bin2hex(random_bytes(4));
        mkdir("$this->dir/backups", 0777, true);
        mkdir("$this->dir/uploads", 0777, true);
        file_put_contents("$this->dir/.htaccess", self::ORIGINAL);
        $GLOBALS['pb_config'] = pb_test_config(['htaccess_file' => "$this->dir/.htaccess", 'backups_dir' => "$this->dir/backups", 'uploads_dir' => "$this->dir/uploads"]);
        pb_migrate();
        pb_set_option('site_title', 'Site');
        pb_set_option('site_url', 'https://exemplo.test');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
    }

    protected function tearDown(): void
    {
        $_SESSION = $_GET = $_POST = [];
        pb_rmtree($this->dir);
    }

    private function file(): string
    {
        return (string) file_get_contents("$this->dir/.htaccess");
    }

    private function picture(int $width, int $height = 100): int
    {
        $png = "$this->dir/src-" . bin2hex(random_bytes(3)) . '.png';
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 210, 78, 43));
        imagepng($image, $png);
        return pb_media_store($png, 'foto.png');
    }

    private function mid(array $media): string
    {
        return pb_uploads_dir() . '/' . pb_media_mid_path($media['path']);
    }

    // ---------------------------------------------------------------- cache

    public function test_the_lines_go_to_the_end_once_and_come_out_leaving_the_file_as_it_was(): void
    {
        $this->assertFalse(pb_cache_on());
        pb_htaccess_block(PB_CACHE_BEGIN, PB_CACHE_END, pb_cache_block(), true, false, 'x');
        $this->assertTrue(pb_cache_on());
        $this->assertStringStartsWith(self::ORIGINAL, $this->file(), 'what was there stays first');
        $this->assertStringEndsWith(PB_CACHE_END . "\n", $this->file());

        pb_htaccess_block(PB_CACHE_BEGIN, PB_CACHE_END, pb_cache_block(), true, false, 'x');
        $this->assertSame(1, substr_count($this->file(), PB_CACHE_BEGIN), 'pressing twice does not stack the block');

        pb_htaccess_block(PB_CACHE_BEGIN, PB_CACHE_END, pb_cache_block(), false, false, 'x');
        $this->assertSame(self::ORIGINAL, $this->file(), 'taken out, the file is exactly the one it was');
    }

    public function test_the_https_lines_and_the_cache_lines_do_not_disturb_each_other(): void
    {
        pb_cache_apply(true);
        pb_https_set(true);
        $both = $this->file();
        $this->assertStringStartsWith(PB_HTTPS_BEGIN, $both);
        $this->assertStringEndsWith(PB_CACHE_END . "\n", $both);

        pb_https_set(false);
        $this->assertTrue(pb_cache_on());
        $this->assertFalse(pb_https_forced());
        pb_cache_apply(false);
        $this->assertFalse(pb_cache_on());
        $this->assertStringContainsString('RewriteRule ^core/', $this->file());
    }

    public function test_the_lines_tell_the_week_from_the_year(): void
    {
        $block = pb_cache_block();
        foreach (['max-age=604800', 'max-age=31536000, immutable', 'v=[0-9]+', '^content/uploads/', 'woff2?'] as $part) {
            $this->assertStringContainsString($part, $block, $part);
        }
        $this->assertStringContainsString('<IfModule mod_headers.c>', $block, 'a host without mod_headers ignores them');
        $this->assertStringContainsString('<IfModule mod_rewrite.c>', $block);
    }

    public function test_a_host_that_answers_with_an_error_after_the_lines_gets_them_taken_out(): void
    {
        $GLOBALS['pb_config']['http'] = fn() => ['status' => 500, 'body' => '', 'headers' => []];
        try {
            pb_cache_apply(true);
            $this->fail('a host that breaks on the lines must not keep them');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('não aceitou', $e->getMessage());
        }
        $this->assertFalse(pb_cache_on());
        $this->assertSame(self::ORIGINAL, $this->file());
    }

    public function test_a_host_that_answers_fine_keeps_them(): void
    {
        $GLOBALS['pb_config']['http'] = fn() => ['status' => 200, 'body' => '', 'headers' => []];
        $this->assertStringContainsString('Pronto', pb_cache_apply(true));
        $this->assertTrue(pb_cache_on());
        $this->assertStringContainsString('Desligado', pb_cache_apply(false));
        $this->assertFalse(pb_cache_on());
    }

    public function test_the_card_in_settings_offers_the_switch_and_the_text_to_paste_when_the_file_cannot_be_written(): void
    {
        $screen = function (): string {
            $_SESSION['user_id'] = $this->admin;
            ob_start();
            pb_admin('GET', '/admin/general');
            return ob_get_clean();
        };
        $off = $screen();
        $this->assertStringContainsString('Velocidade: cache de arquivos', $off);
        $this->assertStringContainsString('action="/admin/general/cache"', $off);
        $this->assertStringContainsString('name="action" value="on"', $off);

        $GLOBALS['pb_config']['http'] = fn() => ['status' => 200, 'body' => '', 'headers' => []];
        pb_cache_apply(true);
        $on = $screen();
        $this->assertStringContainsString('name="action" value="off"', $on);

        $GLOBALS['pb_config']['htaccess_file'] = "$this->dir/no-such-folder/.htaccess";
        $this->assertStringContainsString('# BEGIN PageBrick cache', $screen(), 'the lines are shown to be pasted by hand');
    }

    public function test_a_new_installation_already_ships_with_the_cache_on(): void
    {
        $rules = pb_dotfile_rules()['.htaccess'];
        $this->assertSame(1, substr_count($rules, PB_CACHE_BEGIN));
        $this->assertSame(file_get_contents(dirname(__DIR__) . '/.htaccess'), $rules, 'the file in the repository is the one the installer writes');
    }

    // ---------------------------------------------------------------- pictures

    public function test_a_wide_picture_gets_the_middle_copy_and_a_small_one_does_not(): void
    {
        $wide = pb_media_find($this->picture(3000, 1500));
        $this->assertFileExists($this->mid($wide));
        $this->assertSame(PB_MID_WIDTH, getimagesize($this->mid($wide))[0]);

        $small = pb_media_find($this->picture(700));
        $this->assertFileDoesNotExist($this->mid($small), 'nothing to make smaller');
    }

    public function test_deleting_a_picture_takes_the_middle_copy_with_it(): void
    {
        $media = pb_media_find($this->picture(3000, 1500));
        $mid = $this->mid($media);
        pb_media_delete((int) $media['id']);
        $this->assertFileDoesNotExist($mid);
    }

    public function test_the_srcset_lists_the_copies_that_exist_smallest_first(): void
    {
        $media = pb_media_find($this->picture(3000, 1500));
        $set = pb_media_srcset($media);
        $this->assertMatchesRegularExpression('~-thumb\.\w+ 480w, \S+-960\.\w+ 960w, \S+ 1920w$~', $set);

        $small = pb_media_find($this->picture(700));
        $this->assertMatchesRegularExpression('~^\S+-thumb\.\w+ 480w, \S+ 700w$~', pb_media_srcset($small), 'a picture a bit over the thumbnail has two copies');

        $tiny = pb_media_find($this->picture(300));
        $this->assertSame('', pb_media_srcset($tiny), 'nothing smaller to offer');
    }

    public function test_img_prints_exactly_what_it_always_did_unless_sizes_is_given(): void
    {
        $id = $this->picture(3000, 1500);
        $value = new PbValue(['type' => 'image'], $id);
        $plain = $value->img();
        $this->assertStringNotContainsString('srcset', $plain);
        $this->assertStringNotContainsString('sizes=', $plain);
        $this->assertMatchesRegularExpression('~^<img src="[^"]+" alt="" width="1920" height="960" loading="lazy" decoding="async">$~', $plain);
        $this->assertSame($plain, (string) $value, 'a field printed without asking is the old tag too');

        $this->assertStringContainsString('srcset="', $value->img(sizes: '(min-width: 900px) 560px, 94vw'));
        $this->assertStringContainsString('sizes="(min-width: 900px) 560px, 94vw"', $value->img(sizes: '(min-width: 900px) 560px, 94vw'));
        $this->assertStringContainsString('sizes="auto, 100vw"', $value->img(sizes: 'auto'), 'a browser without sizes=auto falls back to the full width');
        $this->assertStringContainsString('fetchpriority="high"', $value->img(lazy: false, sizes: '100vw'));
        $this->assertStringNotContainsString('srcset', $value->img(size: 'thumb', sizes: '100vw'), 'a thumbnail asked by name stays that one');
    }

    public function test_the_media_screen_offers_to_redo_the_sizes_of_the_old_pictures_a_few_at_a_time(): void
    {
        $media = [];
        for ($i = 0; $i < 14; $i++) {
            $media[] = pb_media_find($this->picture(1500));
        }
        foreach ($media as $m) {
            unlink($this->mid($m)); // as if they were sent before 1.8.0
        }
        $this->assertCount(14, pb_media_without_mid());

        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin('GET', '/admin/media');
        $this->assertStringContainsString('Refazer os tamanhos', ob_get_clean());

        $this->assertSame(12, pb_media_make_mids(12), '12 at a time: the machine of a cheap host decodes each in memory');
        $this->assertCount(2, pb_media_without_mid());
        $this->assertSame(2, pb_media_make_mids(12));
        $this->assertCount(0, pb_media_without_mid());
        $this->assertSame(0, pb_media_make_mids(12));

        ob_start();
        pb_admin('GET', '/admin/media');
        $this->assertStringNotContainsString('Refazer os tamanhos', ob_get_clean(), 'nothing left to offer');
    }

    public function test_a_picture_whose_file_is_gone_is_left_alone(): void
    {
        $media = pb_media_find($this->picture(1500));
        unlink($this->mid($media));
        unlink(pb_uploads_dir() . '/' . $media['path']);
        $this->assertFalse(pb_media_make_mid($media));
    }
}
