<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\TwoFactorLoginResponse as BaseTwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse extends BaseTwoFactorLoginResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        $request->session()->forget('url.intended');

        return parent::toResponse($request);
    }
}
