<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Invitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('adminOnly');
        $sessions = Session::all();
        return response()->json($sessions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $session = new Session([
            'title' => $request->title,
            'description' => $request->description,
            'game_master_id' => auth()->id(),
            'token' => Str::random(60)
        ]);

        $session->save();

        return response()->json([
            'message' => 'Session created successfully!',
            'session' => $session,
            'session_id' => $session->session_id,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    // public function show($token)
    // {
    //     $session = Session::with('players')->findOrFail($token);
    //     return response()->json($session);
    // }

    public function show($token)
    {
        $session = Session::where('token', $token)->with('users')->firstOrFail();

        return response()->json($session);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $session = Session::find($id);
        if (!$session) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'game_master_id' => 'integer|exists:users,user_id',
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $session->update($request->only(['game_master_id', 'title', 'description', 'is_active']));

        return response()->json($session);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $session = Session::find($id);
        if (!$session) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $session->delete();
        return response()->json(['message' => 'Session deleted']);
    }

    /**
     * Rejoindre une session
     */
    public function joinSession(Request $request)
    {
        try {
            Log::info('joinSession called');
            $token = $request->input('session_token');
            Log::info('Token received:', ['session_token' => $token]);

            if (!Auth::check()) {
                Log::warning('User not authenticated');
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            $invitation = Invitation::where('token', $token)->first();
            Log::info('Invitation:', ['invitation' => $invitation]);

            if ($invitation && !$invitation->accepted) {
                $session = $invitation->session;
                Log::info('Session:', ['session' => $session]);

                $user = Auth::user();
                Log::info('Authenticated user:', ['user' => $user]);

                $session->users()->attach($user->id, ['created_at' => now(), 'updated_at' => now()]);
                $invitation->accepted = true;
                $invitation->save();

                Log::info('User joined session successfully');
                return response()->json([
                    'message' => 'You have joined the session!',
                    'session_id' => $session->session_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                Log::warning('Invalid or already used invitation token');
                return response()->json(['message' => 'Invalid or already used invitation token'], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error in joinSession:', ['exception' => $e]);
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Get the sessions created by the authenticated user.
     */
    public function getCreatedSessions()
    {
        $user = Auth::user();
        $sessions = Session::where('game_master_id', $user->id)->get(['title', 'description', 'token']);
        return response()->json($sessions);
    }

    /**
     * Get the sessions the authenticated user is invited to.
     */
    public function getInvitedSessions()
    {
        $user = Auth::user();
        $sessions = $user->sessions()->where('game_master_id', '!=', $user->id)->get(['title', 'description', 'token']);
        return response()->json($sessions);
    }
}