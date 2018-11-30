<?php

/**
 * Route URI's
 */
Route::group(['prefix' => 'youtube'], function() {

    /**
     * Authentication
     */
    Route::get('auth/{id}', function($id)
    {
      Youtube::loadUserId($id);
      return redirect()->to(Youtube::createAuthUrl());
    });

    /**
     * Redirect
     */
    Route::get('callback/{id}', function($id, Illuminate\Http\Request $request)
    {
        if(!$request->has('code')) {
            throw new Exception('$_GET[\'code\'] is not set. Please re-authenticate.');
        }
      
        Youtube::loadUserId($id);
      
        $token = Youtube::authenticate($request->get('code'));

        Youtube::saveAccessTokenToDB($token);

        return redirect(config('youtube.routes.redirect_back_uri', '/'));
    });

});
