<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use AuthenticatesApi,RefreshDatabase;

    public function test_contributions_track_progress_without_creating_expenses_and_can_be_reversed(): void
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $goal = $this->postJson('/api/goals', ['name' => 'Reserva', 'target_amount' => '1000.00', 'initial_amount' => '100.00'], $headers)->assertCreated()->json('data.id');
        $contribution = $this->postJson('/api/goals/'.$goal.'/contributions', ['amount' => '250.55', 'contribution_date' => '2027-01-10'], $headers)->assertCreated()->json('data.id');
        $this->getJson('/api/goals/'.$goal, $headers)->assertJsonPath('data.saved_amount', '350.55')->assertJsonPath('data.progress_percent', '35.05');
        $this->assertSame(0, $user->transactions()->count());
        $this->putJson('/api/goals/'.$goal, ['initial_amount' => '0.00'], $headers)->assertUnprocessable();
        $this->deleteJson('/api/goal-contributions/'.$contribution, [], $headers)->assertOk();
        $this->getJson('/api/goals/'.$goal, $headers)->assertJsonPath('data.saved_amount', '100.00');
        $this->getJson('/api/goals/'.$goal, $this->apiHeaders(User::factory()->create()))->assertNotFound();
    }

    public function test_transfer_cannot_be_overallocated_or_changed_while_linked(): void
    {
        $user = User::factory()->create();
        $headers = $this->apiHeaders($user);
        $first = $user->accounts()->create(['name' => 'Principal', 'account_type' => 'CARTEIRA', 'initial_balance' => '500.00']);
        $second = $user->accounts()->create(['name' => 'Reserva', 'account_type' => 'POUPANCA']);
        $transfer = $this->postJson('/api/transfers', ['source_account_id' => $first->id, 'destination_account_id' => $second->id, 'amount' => '200.00', 'transfer_date' => '2027-01-10', 'status' => 'PAGA'], $headers)->assertCreated()->json('data.id');
        $goal = $this->postJson('/api/goals', ['name' => 'Reserva', 'target_amount' => '1000.00'], $headers)->assertCreated()->json('data.id');
        $data = ['amount' => '150.00', 'contribution_date' => '2027-01-10', 'transfer_id' => $transfer];
        $contribution = $this->postJson('/api/goals/'.$goal.'/contributions', $data, $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/goals/'.$goal.'/contributions', $data, $headers)->assertUnprocessable();
        $this->deleteJson('/api/transfers/'.$transfer, [], $headers)->assertUnprocessable();
        $this->getJson('/api/accounts/'.$first->id, $headers)->assertJsonPath('data.current_balance', '300.00');
        $this->deleteJson('/api/goal-contributions/'.$contribution, [], $headers)->assertOk();
        $this->deleteJson('/api/transfers/'.$transfer, [], $headers)->assertOk();
        $this->getJson('/api/accounts/'.$first->id,$headers)->assertJsonPath('data.current_balance','500.00');
    }
}
