<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth:api', ['except' => ['login', 'register']]);
  }

  public function register(Request $request)
  {
    
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|min:8',
      'email' => 'required|email',
      'password' => 'required|string|min:8|confirmed',
      'role' => 'sometimes|required'
    ]);
    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => $validator->errors()], 400);
    }
    User::create([
      'name' => $request->name,
      'email' => $request->email,
      'password' => bcrypt($request->password),
      'role' => $request->role
    ]);
    return response()->json(['message' => 'Register successfully']);
  }

  /**
   * Get a JWT via given credentials.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function login()
  {
    $credentials = request(['email', 'password']);
    if (!$token = auth()->attempt($credentials)) {
      return response()->json(['error' => 'Invalid credentials. Please check your email and password.'], 401);
    }

    return $this->respondWithToken($token);
  }

  /**
   * Get the authenticated User.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function me()
  {
    return response()->json(auth()->user());
  }

  /**
   * Log the user out (Invalidate the token).
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function logout()
  {
    auth()->logout();

    return response()->json(['message' => 'Successfully logged out']);
  }

  /**
   * Refresh a token.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function refresh()
  {
    return $this->respondWithToken(auth()->refresh());
  }

  /**
   * Get the token array structure.
   *
   * @param  string $token
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function resetPassword(Request $request)
  {
    $user = auth()->user();

    $request->validate([
      'current_password' => 'required|string',
      'new_password' => 'required|string|min:8|confirmed',
    ]);

    if (!Hash::check($request->current_password, $user->password)) {
      return response()->json(['error' => 'Current password is incorrect'], 401);
    }

    $user->update(['password' => bcrypt($request->new_password)]);

    return response()->json(['message' => 'Password changed successfully']);
  }
  protected function respondWithToken($token)
  {
    return response()->json([
      'access_token' => $token,
      'token_type' => 'bearer',
      'expires_in' => auth()->factory()->getTTL() * 60
    ]);
  }
}
