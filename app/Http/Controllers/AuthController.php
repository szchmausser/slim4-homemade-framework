<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\RememberMe;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends Controller
{
    public function show(Request $request, Response $response): Response
    {
        if (Auth::check()) {
            return $response->withStatus(303)->withHeader('Location', '/tareas');
        }

        return $this->render($request, $response, 'auth/login.twig', [
            'active'     => 'login',
            'flash_info' => Flash::get('info'),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $email = trim((string) ($data['email'] ?? ''));

        $validator = Validator::make($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');

            return $this->render($request, $response, 'auth/login.twig', [
                'active'     => 'login',
                'flash_info' => Flash::get('info'),
                'email'      => $email,
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        // Dummy bcrypt cuando el email no existe: así el tiempo de respuesta
        // no delata si la cuenta existe o no (enumeración por timing).
        $hash = $user?->password_hash
            ?? '$2y$10$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG';
        $password = (string) ($data['password'] ?? '');

        if ($user === null || !password_verify($password, $hash)) {
            // Genérico a propósito: nunca decir si falló el email o la clave.
            Flash::set('error', 'Credenciales inválidas.');

            return $this->render($request, $response, 'auth/login.twig', [
                'active'     => 'login',
                'flash_info' => Flash::get('info'),
                'email'      => $email,
            ]);
        }

        // Rehash silencioso si cambió el algoritmo o el costo.
        if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }

        Auth::login($user->id);

        $response = $response->withStatus(303)->withHeader('Location', '/tareas');

        if (!empty($data['remember'])) {
            $response = $response->withAddedHeader(
                'Set-Cookie',
                RememberMe::cookieHeader(RememberMe::create($user->id))
            );
        }

        return $response;
    }

    public function destroy(Request $request, Response $response): Response
    {
        $id = Auth::id();
        if ($id !== null) {
            RememberMe::clearUser($id);
        }

        Auth::logout();
        Flash::set('info', 'Sesión cerrada.');

        $response = $response->withStatus(303)->withHeader('Location', '/login');

        return $response->withAddedHeader('Set-Cookie', RememberMe::cookieHeader(clear: true));
    }
}
