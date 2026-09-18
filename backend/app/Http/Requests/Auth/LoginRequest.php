<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Vérifie les identifiants SANS authentifier la requête.
     *
     * Auth::attempt() journalisait l'utilisateur — cookie de session posé —
     * avant même de savoir si un second facteur était requis. Combiné au mode
     * Sanctum « stateful » (actif pour toute origine déclarée dans
     * SANCTUM_STATEFUL_DOMAINS, « localhost » par défaut faute de valeur
     * explicite), un appelant qui se déclarait Origin: http://localhost
     * obtenait une session valide avec le seul mot de passe : la 2FA devenait
     * une formalité d'interface, sans jamais bloquer l'accès. Auth::validate()
     * confirme les identifiants sans ouvrir de session ; c'est au contrôleur,
     * une fois le second facteur passé (ou absent), d'appeler Auth::login().
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validerIdentifiants(): \App\Models\User
    {
        try {
            $this->ensureIsNotRateLimited();

            if (! Auth::guard('web')->validate($this->only('email', 'password'))) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'email' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
                ]);
            }

            RateLimiter::clear($this->throttleKey());

            /** @var \App\Models\User $utilisateur */
            $utilisateur = Auth::guard('web')->getLastAttempted();

            return $utilisateur;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Erreur d\'authentification: ' . $e->getMessage());
            
            throw ValidationException::withMessages([
                'email' => 'Une erreur technique est survenue. Veuillez réessayer plus tard.',
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
