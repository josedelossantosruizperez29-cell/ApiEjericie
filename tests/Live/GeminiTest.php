<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LocalPostgres;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (getenv('FIT_LIVE_GEMINI') !== '1') $this->markTestSkipped('Requiere activación explícita.');
    $this->instance=new LocalPostgres;
    $this->instance->create();
});

afterEach(function () {
    if (isset($this->instance)) $this->instance->close();
});

it('genera un plan real con EVO 500 y lo recupera mediante la API', function () {
    $machine=DB::table('equipment')->where('slug','evo-500')->sole();
    $guest=$this->postJson('/api/v1/guests',['name'=>'Prueba','age_years'=>30,'height_cm'=>170,'weight_kg'=>70,'timezone'=>'UTC',
        'level'=>'beginner','goal'=>'strength_cardio','weekly_availability'=>[now()->addDay()->dayOfWeekIso],'session_minutes'=>20,
        'equipment_ids'=>[$machine->id],'complete'=>true])->json('data');
    $headers=['X-Guest-Id'=>$guest['public_id'],'Authorization'=>'Bearer '.$guest['token']];
    $result=$this->postJson('/api/v1/ai/training-plans/generate',['request_key'=>(string)Str::uuid()],$headers);
    if ($result->status()!==200) $this->fail('Generación: HTTP '.$result->status().' / '.($result->json('errors.reason')??'unexpected'));
    $plan=$result->json('data.ai_plan_recommendations.0.training_plan_id');
    expect($plan)->not->toBeNull();
    $this->getJson('/api/v1/training-plans/'.$plan,$headers)->assertOk()->assertJsonPath('data.id',$plan);
    expect(DB::table('training_plans')->count())->toBe(1)->and(DB::table('workout_exercises')->count())->toBeGreaterThan(0);
    fwrite(STDOUT,'Plan confirmado en PostgreSQL: '.DB::table('workout_sessions')->count().' jornadas, '.DB::table('workout_exercises')->count().' ejercicios; consumo '.json_encode($result->json('data.usage')).PHP_EOL);
});
