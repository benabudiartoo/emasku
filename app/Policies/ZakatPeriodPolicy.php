<?php

namespace App\Policies;

/** A zakat period is visible only to its owner; anyone else gets 404 (see OwnerPolicy). */
class ZakatPeriodPolicy extends OwnerPolicy
{
}
