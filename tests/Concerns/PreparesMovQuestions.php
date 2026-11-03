<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

trait PreparesMovQuestions
{
    private function prepareMovQuestions(string $reference): string
    {
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test', 'spms.ai.model' => 'test']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['questions' => [['id' => 'q1', 'label' => 'How many target outputs did you complete?', 'type' => 'number', 'required' => true], ['id' => 'q2', 'label' => 'Describe the work evidenced by these files.', 'type' => 'text', 'required' => true]], 'guidance' => 'Attach readable files demonstrating the target.'])]]]]])]);
        $response = $this->post(route('movs.questions'), ['target_ref' => $reference])->assertSessionHasNoErrors()->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        return $query['questionnaire_token'];
    }
}
