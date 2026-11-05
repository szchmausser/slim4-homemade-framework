<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\RememberMe;
use App\Support\Throttle;
use App\Support\Url;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends Controller
{
    /**
     * Hash bcrypt valido de una clave aleatoria, con el mismo coste que usa
     * password_hash(PASSWORD_DEFAULT) en PHP >= 8.4. Cuando el email no existe
     * se verifica contra este hash en vez de saltarse password_verify: un hash
     * invalido (o de otro coste) tarda distinto y vuelve a enumerar cuentas
     * por timing. Debe tener 60 caracteres o bcrypt lo rechaza al instante.
     */
    public const DUMMY_HASH = '$2y$12$hmGwg9QWNfE1u1JP/s90ReQugq9oOeSa4YlxVNYaQFX3/eCtS84G2';

    /** Mismo mensaje para credenciales malas y para cuenta throttled. */
    private const INVALID_CREDENTIALS = 'Credenciales inválidas.';

    public function show(Request $request, Response $response): Response
    {
        if (Auth::check()) {
            return $response->withStatus(303)->withHeader('Location', Url::path('/tareas'));
        }

        return $this->render($request, $response, 'auth/login.twig', [
            'active' => 'login',
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
                'active' => 'login',
                'email'  => $email,
            ]);
        }

        // IP del cliente para el throttling. En CLI/tests puede no venir.
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'desconocida');

        // Antes de tocar la base de usuarios: si ya hay demasiados fallos, no
        // se revela nada (ni bloqueo ni existencia) y no se gasta bcrypt.
        if (Throttle::tooMany($email, $ip)) {
            Flash::set('error', self::INVALID_CREDENTIALS);

            return $this->render($request, $response, 'auth/login.twig', [
                'active' => 'login',
                'email'  => $email,
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        // Dummy bcrypt cuando el email no existe: así el tiempo de respuesta
        // no delata si la cuenta existe o no (enumeración por timing).
        $hash = $user === null ? self::DUMMY_HASH : $user->password_hash;
        $password = (string) ($data['password'] ?? '');

        if ($user === null || !password_verify($password, $hash)) {
            Throttle::hit($email, $ip);

            // Genérico a propósito: nunca decir si falló el email o la clave.
            Flash::set('error', self::INVALID_CREDENTIALS);

            return $this->render($request, $response, 'auth/login.twig', [
                'active' => 'login',
                'email'  => $email,
            ]);
        }

        // Login válido: se perdona el historial de fallos de este email + IP.
        Throttle::clear($email, $ip);

        // Rehash silencioso si cambió el algoritmo o el costo.
        if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }

        Auth::login($user->id);

        $response = $response->withStatus(303)->withHeader('Location', Url::path('/tareas'));

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

        $response = $response->withStatus(303)->withHeader('Location', Url::path('/login'));

        return $response->withAddedHeader('Set-Cookie', RememberMe::cookieHeader(clear: true));
    }
}
