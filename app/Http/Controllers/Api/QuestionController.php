<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $questions = Question::with('options')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }

    public function store(StoreQuestionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $question = DB::transaction(function () use ($data) {

            $question = Question::create([
                'question_text' => $data['question_text'],
                'type' => $data['type'],
                'marks' => $data['marks'],
                'explanation' => $data['explanation'] ?? null,
            ]);

            foreach ($data['options'] as $option) {

                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'],
                    'sort_order' => $option['sort_order'],
                ]);
            }

            return $question;
        });

        $question->load('options');

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully.',
            'data' => $question,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $question = Question::with('options')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $question,
        ]);
    }

    public function update(
        UpdateQuestionRequest $request,
        string $id
    ): JsonResponse {

        $question = Question::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | Protect historical quiz attempts
        |--------------------------------------------------------------------------
        */

        if (
            $question->attemptQuestions()->exists() ||
            $question->answers()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This question cannot be edited because it has already been used in a quiz attempt.',
            ], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($question, $data) {

            $question->update([
                'question_text' => $data['question_text']
                    ?? $question->question_text,

                'type' => $data['type']
                    ?? $question->type,

                'marks' => $data['marks']
                    ?? $question->marks,

                'explanation' => array_key_exists('explanation', $data)
                    ? $data['explanation']
                    : $question->explanation,
            ]);

            if (isset($data['options'])) {

                $question->options()->delete();

                foreach ($data['options'] as $option) {

                    $question->options()->create([
                        'option_text' => $option['option_text'],
                        'is_correct' => $option['is_correct'],
                        'sort_order' => $option['sort_order'],
                    ]);
                }
            }
        });

        $question->load('options');

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully.',
            'data' => $question,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $question = Question::findOrFail($id);

        if ($question->attemptQuestions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This question cannot be deleted because it has already been used in a quiz attempt.',
            ], 422);
        }

        if ($question->quizzes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Remove this question from its quizzes before deleting it.',
            ], 422);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully.',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->role === 'admin',
            403,
            'Admin access required.'
        );
    }
}