<?php pb_add_route("GET", "/quebra", function () { echo "meio caminho"; throw new RuntimeException("rota quebrou"); });
