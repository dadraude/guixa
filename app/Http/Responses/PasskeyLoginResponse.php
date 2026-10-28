<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Passkeys\Http\Responses\PasskeyLoginResponse as BasePasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse extends BasePasskeyLoginResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        $request->session()->forget('url.intended');

        return parent::toResponse($request);
    }
}
