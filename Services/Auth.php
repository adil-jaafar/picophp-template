<?php

namespace Services;

use PicoPHP\asSingleton;
use PicoPHP\Services\DB;
use PicoPHP\Services\Request;
use PicoPHP\Services\Response;

class Auth implements asSingleton
{
    public $user = null;

    public function __construct(public Request $request, public Response $response, public DB $db)
    {
        $session_id = $request->cookie('session_id', null);
        if (!$session_id) return;

        $session = $this->db->fetchOne("SELECT * FROM user_sessions WHERE session_id = :session_id AND is_active = 1 AND expires_at > NOW()", ["session_id" => $session_id]);
        if ($session && ($user = $this->getUserProfile($session['user_id']))) {
            $this->setUser($user);
            return;
        }

        $this->logout();
    }

    // get all needed data for user profile
    private function getUserProfile($user)
    {
        // Check if $user is a string or number
        if (is_string($user) || is_numeric($user)) {
            $user_id = $user;
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = :id", ["id" => $user_id]);
        }

        if ($user) {
            // unset password and other sensitive data
            unset($user['password']);
            return $user;
        }

        return null;
    }

    public function loginWithEmailPassword($email, $password)
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE email = :email", ["email" => $email]);
        if ($user && password_verify($password, $user['password'])) {

            $expires = 4 * 60; // en minutes
            $session = [
                "session_id" => $this->guidv4(),
                "user_id" => $user['id'],
                "ip_address" => $this->request->ip(),
                "user_agent" => $this->request->userAgent(),
                "created_at" => DB::raw("NOW()"),
                "expires_at" => DB::raw("DATE_ADD(NOW(), INTERVAL $expires MINUTE)"),
                "is_active" => 1,
            ];

            $this->db->insert("user_sessions", $session);
            $this->response->cookie("session_id", $session['session_id'], expires: 30 * 60, httponly: true);

            $user_profile = $this->getUserProfile($user);
            $this->setUser($user_profile);
            return true;
        }

        return false;
    }

    private function setUser($user)
    {
        $this->user = $user;
    }

    public function logout()
    {
        $session_id = $this->request->cookie('session_id', null);
        if ($session_id) {
            $this->db->delete("user_sessions", where: "session_id = :session_id", whereParams: ["session_id" => $session_id]);
        }
        $this->response->cookie("session_id", "", expires: -1, httponly: true);
        $this->user = null;
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function guidv4($data = null)
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
