<?php

namespace App\Http\Middleware;

use App\Enums\Service as EnumService;
use App\Helpers\CurrencyHelper;
use App\Http\Controllers\BaseApiController;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WhatsappRate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckWalletBalance extends BaseApiController
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Assuming the authenticated user has a 'wallet_balance' attribute
        $user = Auth::user();
        $serviceId = Service::where('name', EnumService::OTHER)->value('id');
        $wallet = Wallet::where(['user_id' => Auth::id(), 'service_id' => $serviceId, 'status' => 'active'])->first();

        // Check if the user is logged in and has a wallet
        if ($user && isset($wallet)) {
            // Set a minimum balance from Whatsapp Rate
            $minimumDolarBalance = WhatsappRate::selectRaw('LEAST(marketing, utility,
             authentication, authentication_international, service) as min_value')
                ->value('min_value');
            $minimumBalance = CurrencyHelper::convertDollarToSAR($minimumDolarBalance);

            if ($wallet->amount < $minimumBalance) {
                return $this->response(false, 'Insufficient wallet balance.', null, 403);
            }
        } else {

            return $this->response(false, 'User not authenticated or wallet balance unavailable .', null, 401);
        }

        return $next($request);
    }
}
