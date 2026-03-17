<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use TurboStreamExport\Jobs\ProcessExportJob;

beforeEach(function () {
    Queue::fake();
});

describe('Export API Endpoints', function () {
    it('can create an export job', function () {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/exports', [
                'model' => User::class,
                'columns' => ['id', 'name', 'email'],
                'format' => 'csv',
            ]);
        
        $response->assertStatus(202)
            ->assertJsonStructure([
                'export_id',
                'status',
                'message',
            ]);
    });

    it('returns 401 for unauthorized export creation', function () {
        $response = $this->postJson('/api/exports', [
            'model' => User::class,
            'columns' => ['id', 'name'],
        ]);
        
        $response->assertStatus(401);
    });

    it('can check export progress', function () {
        $response = $this->getJson('/api/exports/test-export-id/progress');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'progress',
                'total',
                'status',
            ]);
    });
});
