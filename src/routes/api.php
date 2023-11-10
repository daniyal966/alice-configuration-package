<?php


use Illuminate\Http\Request;
use Alice\Configuration\Http\Controllers\AliceController;
use Alice\Configuration\Models\KycToken;


Route::group(['namespace'=>'Alice\Configuration\Http\Controllers'], function(){
    // Route::post('contact-alice','AliceController@index')->name('contactALice'); 

    Route::get('authenticate', [AliceController::class , 'authenticate']);
    Route::post('create-alice-user', [AliceController::class , 'createAliceKycUser']);
    Route::post('backend-token-with-userid', [AliceController::class , 'backendTokenWithUserId']);


    Route::post('/validate-kyc-token' , [AliceController::class , 'performKyc']);
    Route::post('/kyc-user-report' , [AliceController::class , 'getKycUserReport']);
    Route::post('/update-user-status' , [AliceController::class , 'updateUserStatusAfterDocumentCheck']);




});