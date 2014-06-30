<?php

Route::get('facebook/social-user-authentication', 'Codedazur\Social\FacebookController@socialUserAuthenticationAction');
Route::get('facebook/social-authentication-callback', 'Codedazur\Social\FacebookController@socialAuthenticationCallbackAction');
Route::get('twitter/social-user-authentication', 'Codedazur\Social\TwitterController@socialUserAuthenticationAction');
Route::get('twitter/social-authentication-callback', 'Codedazur\Social\TwitterController@socialAuthenticationCallbackAction');
