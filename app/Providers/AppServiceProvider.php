<?php

namespace App\Providers;

use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\Deal;
use App\Models\LiveChatMessage;
use App\Models\Organization;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTextMessage;
use App\Models\WhatsappVideoMessage;
use App\Models\Workspace;
use App\Models\OrganizationUser;
use App\Notifications\Channels\SmsChannel;
use App\Repositories\BalanceTransferRepository;
use App\Repositories\BalanceTransferRepositoryInterface;
use App\Repositories\ContactGroupsRepository;
use App\Repositories\ContactGroupsRepositoryInterface;
use App\Repositories\FavoritSmsRepository;
use App\Repositories\FavoritSmsRepositoryInterface;
use App\Repositories\OutboxRepository;
use App\Repositories\OutboxRepositoryInterface;
use App\Repositories\PaymentsRepository;
use App\Repositories\PaymentsRepositoryInterface;
use App\Repositories\RegularSmsRepository;
use App\Repositories\RegularSmsRepositoryInterface;
use App\Repositories\SenderRepository;
use App\Repositories\SenderRepositoryInterface;
use App\Repositories\TicketsRepository;
use App\Repositories\TicketsRepositoryInterface;
use App\Repositories\UserOtpRepository;
use App\Repositories\UserOtpRepositoryInterface;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserProfileRepositoryInterface;
use App\Repositories\WhitelistipRepository;
use App\Repositories\WhitelistipRepositoryInterface;
use App\Services\Sms;
use Database\Seeders\ProductionAdminUserSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SenderRepositoryInterface::class, SenderRepository::class);
        $this->app->bind(TicketsRepositoryInterface::class, TicketsRepository::class);
        $this->app->bind(ContactGroupsRepositoryInterface::class, ContactGroupsRepository::class);
        $this->app->bind(BalanceTransferRepositoryInterface::class, BalanceTransferRepository::class);
        $this->app->bind(FavoritSmsRepositoryInterface::class, FavoritSmsRepository::class);
        $this->app->bind(RegularSmsRepositoryInterface::class, RegularSmsRepository::class);
        $this->app->bind(UserOtpRepositoryInterface::class, UserOtpRepository::class);
        $this->app->bind(WhitelistipRepositoryInterface::class, WhitelistipRepository::class);
        $this->app->bind(PaymentsRepositoryInterface::class, PaymentsRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);
        $this->app->bind(OutboxRepositoryInterface::class, OutboxRepository::class);
        Passport::enablePasswordGrant();
    }

    public function boot(): void
    {
        Validator::extend('exists_in_user_table', function ($attribute, $value) {
            return User::where('username', $value)->exists();
        });

        Validator::replacer('exists_in_user_table', function ($message, $attribute) {
            return str_replace(':attribute', $attribute, 'The :attribute does not exist.');
        });

        Passport::hashClientSecrets();

        Notification::extend('sms', function ($app) {
            return new SmsChannel($app->make(Sms::class));
        });

        Relation::morphMap([
            'Task' => Task::class,
            'Deal' => Deal::class,
            'App\Models\ContactEntity' => ContactEntity::class,
            'App\Models\Organization' => Organization::class,
            'App\Models\WhatsappMessage' => WhatsappMessage::class,
            'App\Models\Supervisor' => Supervisor::class,
            'App\Models\LiveChatMessage' => LiveChatMessage::class,
            'App\Models\Channel' => Channel::class,
            'App\Models\WhatsappPhoneNumber' => WhatsappPhoneNumber::class,
            'App\Models\WhatsappImageMessage' => WhatsappImageMessage::class,
            'App\Models\WhatsappVideoMessage' => WhatsappVideoMessage::class,
            'App\Models\WhatsappAudioMessage' => WhatsappAudioMessage::class,
            'App\Models\WhatsappTextMessage' => WhatsappTextMessage::class,
            'App\Models\Workspace' => Workspace::class,
            'App\Models\OrganizationUser' => OrganizationUser::class,
            'App\Models\User' => User::class, // for system if needed
            'contacts' => ContactEntity::class,

        ]);

        // Only run seeder check during HTTP requests, not during artisan commands
        if (!$this->app->runningInConsole()) {
            try {
                // if (!Supervisor::where('username', '=', 'dreams.admin')->exists()) {
                //     Artisan::call('db:seed', [
                //         '--class' => ProductionAdminUserSeeder::class,
                //         '--force' => true
                //     ]);
                // }
            } catch (\Exception $e) {
                // Database not ready yet, skip seeder check
            }
        }

        // Register message observers to update conversation.last_message_at
        WhatsappMessage::observe(\App\Observers\MessageObserver::class);
        LiveChatMessage::observe(\App\Observers\MessageObserver::class);
        \App\Models\MessengerMessage::observe(\App\Observers\MessageObserver::class);
    }
}
