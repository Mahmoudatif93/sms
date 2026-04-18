<?php

use App\Mail\UserInvitationMail;
use Illuminate\Support\Facades\Route;
use App\Jobs\ProcessInboxAgentBillingsJob;
Route::get('/', function () {
    return view('welcome');
});


Route::get('/docs', function () {
    ProcessInboxAgentBillingsJob::dispatch();
    return view('swagger-ui');
});

//Route::get('/preview-invitation-mail', function () {
//    $mailable = new UserInvitationMail("https://google.com"); // Modify as needed
//
//    Mail::to("menna.alsherif2@gmail.com")->send(new UserInvitationMail("https://google.com"));
//    return $mailable->render();
//});


Route::get('/test-invite', function () {
    Mail::to('menna.alsherif2@gmail.com')->send(new \App\Mail\UserInvitationMail('https://test-link.com'));
    return 'sent!';
});
