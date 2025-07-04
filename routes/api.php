<?php

use App\Services\RabbitMQService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post("/message", function (Request $request) {
    $message = $_POST['message'];
    $mqService = new RabbitMQService();
    // $mqService->publishTopic($message, 'order.created');
    $mqService->publishFanout($message);
    return view('welcome');
});