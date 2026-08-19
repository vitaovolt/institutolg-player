<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaGestaoDoAcervo;

class UserPolicy
{
    use AutorizaGestaoDoAcervo;
}
