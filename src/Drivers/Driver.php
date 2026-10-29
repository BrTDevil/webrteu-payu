<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Drivers;

use WeBRTeu\Payments\Contracts\Capturable;
use WeBRTeu\Payments\Contracts\Gateway;
use WeBRTeu\Payments\Contracts\Refundable;

/**
 * Cele două platforme PayU (România v4 și GPO Europe) expun aceleași operații.
 */
interface Driver extends Capturable, Gateway, Refundable {}
