<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    // Telescope يُحمَّل فقط إن كانت الحزمة مثبّتة (vendor) — حتى لا يتوقف الموقع لو نُسي composer install
    class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class) ? App\Providers\TelescopeServiceProvider::class : null,
]));
