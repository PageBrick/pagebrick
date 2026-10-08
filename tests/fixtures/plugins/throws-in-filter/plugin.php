<?php pb_add_filter("slot:test", function (string $html): string { throw new LogicException("quebrou no filtro"); });
