<?php

namespace App\Http\Controllers;

use App\Models\AssistantMessage;
use App\Services\Ai\Assistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The role-aware assistant and the person's own, deletable, history. */
class AssistantController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Assistant/Index', [
            'messages' => AssistantMessage::where('user_id', $request->user()->id)->oldest('id')->limit(200)->get()
                ->map(fn (AssistantMessage $m) => ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'links' => $m->links ?? [], 'at' => $m->created_at?->toIso8601String()]),
        ]);
    }

    public function ask(Request $request, Assistant $assistant): RedirectResponse
    {
        $question = $request->validate(['question' => ['required', 'string', 'max:1000']])['question'];
        $assistant->ask($request->user(), $question);

        return back();
    }

    public function destroy(Request $request, AssistantMessage $message): RedirectResponse
    {
        abort_unless($message->user_id === $request->user()->id, 404);
        $message->delete();

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        AssistantMessage::where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Your assistant history is deleted.');
    }
}
