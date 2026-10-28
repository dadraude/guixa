<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\LoginResponse as BaseLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse extends BaseLoginResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        $request->session()->forget('url.intended');

        return parent::toResponse($request);
    }
}
