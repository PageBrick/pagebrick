<?php
// Works until a test sets $GLOBALS["break_pages"]: then every page header throws, like a plugin incompatible with a new core.
pb_add_filter("head_html", function (string $html): string {
    if (!empty($GLOBALS["break_pages"])) {
        throw new RuntimeException("incompatível com a versão nova");
    }
    return $html;
});
