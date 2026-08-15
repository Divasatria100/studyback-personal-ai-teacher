<?php

namespace Tests\Feature;

use App\Models\Chunk;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\StudySession;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Learning State (Database Design §8, API Design §10): mastery is the cumulative
 * average of every historical answer; status thresholds are fixed (<60
 * needs_review, 60–79 in_progress, ≥80 mastered); weak topics surface in the
 * topics tree and reuse the same Teach Me capability with intent=review.
 */
class LearningStateTest extends TestCase
{
    use RefreshDatabase;

    private function seedSubtopicWithQuestions(User $user, int $correctAnswers, int $totalAnswers): array
    {
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id, 'order_index' => 0]);

        // Build a completed quiz + questions so recalculateMastery has real rows to average.
        $session = StudySession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
            'status' => 'completed',
        ]);
        $quiz = Quiz::factory()->create([
            'study_session_id' => $session->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'status' => 'completed',
        ]);

        for ($index = 0; $index < $totalAnswers; $index++) {
            $question = QuizQuestion::query()->create([
                'quiz_id' => $quiz->id,
                'subtopic_id' => $subtopic->id,
                'question_type' => 'multiple_choice',
                'question_text' => 'Question '.($index + 1),
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
                'order_index' => $index,
            ]);

            $isCorrect = $index < $correctAnswers;

            QuizAnswer::query()->create([
                'quiz_question_id' => $question->id,
                'submitted_answer' => $isCorrect ? 'A' : 'B',
                'is_correct' => $isCorrect,
                'ai_feedback' => $isCorrect ? 'Correct' : 'Wrong',
                'answered_at' => Carbon::now(),
            ]);
        }

        return [$material, $topic, $subtopic];
    }

    private function recalc(Subtopic $subtopic): Subtopic
    {
        return app(SubtopicRepositoryInterface::class)->recalculateMastery($subtopic->id);
    }

    public function test_mastery_below_60_is_needs_review(): void
    {
        $user = User::factory()->create();
        [, , $subtopic] = $this->seedSubtopicWithQuestions($user, 2, 5); // 40% correct

        $updated = $this->recalc($subtopic);

        $this->assertSame(40.0, (float) $updated->mastery_score);
        $this->assertSame('needs_review', $updated->status);
    }

    public function test_mastery_between_60_and_79_is_in_progress(): void
    {
        $user = User::factory()->create();
        [, , $subtopic] = $this->seedSubtopicWithQuestions($user, 3, 5); // 60% correct

        $updated = $this->recalc($subtopic);

        $this->assertSame(60.0, (float) $updated->mastery_score);
        $this->assertSame('in_progress', $updated->status);
    }

    public function test_mastery_of_80_or_above_is_mastered(): void
    {
        $user = User::factory()->create();
        [, , $subtopic] = $this->seedSubtopicWithQuestions($user, 4, 5); // 80% correct

        $updated = $this->recalc($subtopic);

        $this->assertSame(80.0, (float) $updated->mastery_score);
        $this->assertSame('mastered', $updated->status);
    }

    public function test_mastery_with_no_answers_stays_zero_and_needs_review(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id]);
        $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id]);

        $updated = $this->recalc($subtopic);

        $this->assertSame(0.0, (float) $updated->mastery_score);
        $this->assertSame('needs_review', $updated->status);
    }

    public function test_mastery_is_cumulative_across_quizzes(): void
    {
        $user = User::factory()->create();
        [$material, $topic, $subtopic] = $this->seedSubtopicWithQuestions($user, 3, 5); // 60%

        // A second historical record across a later quiz moves the cumulative average to 66.67%.
        $secondQuiz = Quiz::factory()->create([
            'study_session_id' => $subtopic->topic->material->studySessions()->first()->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'status' => 'completed',
        ]);
        $extraQuestion = QuizQuestion::query()->create([
            'quiz_id' => $secondQuiz->id,
            'subtopic_id' => $subtopic->id,
            'question_type' => 'multiple_choice',
            'question_text' => 'Extra question',
            'options' => ['A', 'B'],
            'correct_answer' => 'A',
            'order_index' => 0,
        ]);
        QuizAnswer::query()->create([
            'quiz_question_id' => $extraQuestion->id,
            'submitted_answer' => 'A',
            'is_correct' => true,
            'ai_feedback' => 'Correct',
            'answered_at' => Carbon::now(),
        ]);

        $updated = $this->recalc($subtopic);

        $this->assertSame(66.67, round((float) $updated->mastery_score, 2));
        $this->assertSame('in_progress', $updated->status);
    }

    public function test_weak_subtopics_are_listed_in_topics_tree_with_status(): void
    {
        $user = User::factory()->create();
        [$material, , $subtopic] = $this->seedSubtopicWithQuestions($user, 1, 4); // 25% → needs_review
        $this->recalc($subtopic);

        $response = $this->actingAs($user)->getJson("/api/materials/{$material->id}/topics");

        $response->assertOk()
            ->assertJsonPath('material_id', $material->id)
            ->assertJsonPath('topics.0.subtopics.0.status', 'needs_review')
            ->assertJsonPath('topics.0.subtopics.0.mastery_score', 25);
    }

    public function test_weak_topic_review_uses_same_teach_me_capability(): void
    {
        $user = User::factory()->create();
        [$material, $topic, $subtopic] = $this->seedSubtopicWithQuestions($user, 1, 4);
        Chunk::factory()->create([
            'material_id' => $material->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'chunk_index' => 0,
        ]);

        $session = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'teach_me',
            'topic_ids' => [$topic->id],
        ])->json();

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/explanations", [
            'subtopic_id' => $subtopic->id,
            'intent' => 'review',
        ]);

        $response->assertOk()
            ->assertJsonPath('subtopic_id', $subtopic->id)
            ->assertJsonStructure(['explanation']);
    }

    public function test_retake_for_weak_subtopic_raises_mastery_over_time(): void
    {
        $user = User::factory()->create();
        [$material, $topic, $subtopic] = $this->seedSubtopicWithQuestions($user, 1, 5); // 20% → needs_review

        // Retake: 5 more questions, 4 correct → cumulative (1+4)/10 = 50%.
        $retake = Quiz::factory()->create([
            'study_session_id' => $subtopic->topic->material->studySessions()->first()->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'status' => 'completed',
        ]);
        for ($index = 0; $index < 5; $index++) {
            $question = QuizQuestion::query()->create([
                'quiz_id' => $retake->id,
                'subtopic_id' => $subtopic->id,
                'question_type' => 'multiple_choice',
                'question_text' => 'Retake question '.($index + 1),
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
                'order_index' => $index,
            ]);

            $isCorrect = $index < 4;

            QuizAnswer::query()->create([
                'quiz_question_id' => $question->id,
                'submitted_answer' => $isCorrect ? 'A' : 'B',
                'is_correct' => $isCorrect,
                'ai_feedback' => $isCorrect ? 'Correct' : 'Wrong',
                'answered_at' => Carbon::now(),
            ]);
        }

        $updated = $this->recalc($subtopic);

        $this->assertSame(50.0, (float) $updated->mastery_score);
        $this->assertSame('needs_review', $updated->status);
    }
}
