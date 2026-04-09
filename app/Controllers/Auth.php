<?php
namespace App\Controllers;

use App\Models\UsuarioModel;
use CodeIgniter\Controller;

class Auth extends Controller
{
    public function login()
    {
        if (session()->get('usuario_id')) {
            return redirect()->to('/dashboard');
        }
        return view('auth/login', ['title' => 'Iniciar Sesión']);
    }

    public function doLogin()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $model = new UsuarioModel();
        $usuario = $model->where('email', $email)->first();

        if ($usuario && password_verify($password, $usuario['password'])) {
            session()->set([
                'usuario_id' => $usuario['id'],
                'usuario_nombre' => $usuario['nombre'],
                'usuario_email' => $usuario['email'],
            ]);
            return redirect()->to('/dashboard');
        }

        return redirect()->back()->with('error', 'Credenciales inválidas.');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
