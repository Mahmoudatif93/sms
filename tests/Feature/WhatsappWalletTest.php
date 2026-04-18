<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Traits\WhatsappBillingTrait;
class WhatsappWalletTest extends TestCase
{
    use WhatsappBillingTrait;
    public function test_whatsup_wallet_function()
    {
        return $this->WhatsappWallet( 'marketing', '01431793dca4378f1b6476b8d0f92a3e', '+201099922302');

    }
}
