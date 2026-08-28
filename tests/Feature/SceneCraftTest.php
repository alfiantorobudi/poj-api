<?php

use App\Ai\Agents\SceneCraftAgent;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from scenecraft page', function () {
    $response = $this->get(route('scenecraft'));

    $response->assertRedirect(route('login'));
});

test('authenticated user can visit scenecraft page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('scenecraft'));

    $response->assertOk();
    $response->assertSeeLivewire('scenecraft-canvas');
});

test('user can load sample preset concepts', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('scenecraft-canvas')
        ->call('loadSample', 'coffee_brand')
        ->assertSet('targetDuration', 15)
        ->assertSet('visualStyle', 'Cinematic 35mm Film')
        ->assertSet('aspectRatio', '9:16')
        ->assertSet('pacing', 'Slow & Atmospheric')
        ->call('loadSample', 'scifi_trailer')
        ->assertSet('targetDuration', 60)
        ->assertSet('aspectRatio', '2.39:1');
});

test('generating storyboard with ai parses structured scenes correctly', function () {
    $mockOutput = <<<'AI'
Here is your cinematic visual storyboard:

===SCENE_START===
SCENE_NUMBER: 1
TITLE: Neon Rain Sprint
DURATION_SECONDS: 6
VISUAL: Extreme close-up of a high-tech sneaker hitting a wet neon puddle with light trails.
SHOT_TYPE: Extreme Close-Up
LENS: 85mm f/1.4
ANGLE: Ground-Level Low Angle
LIGHTING: Moody Cyberpunk Neon Cyan & Magenta
MOVEMENT: High-Speed Tracking
NARRATION: Beyond limits, speed begins.
AUDIO_SFX: [SFX: Water splash with synth bass drop]
IMAGE_PROMPT: Ground level close-up of futuristic sneaker in neon puddle, 35mm film still, 8k --ar 16:9
===SCENE_END===

===SCENE_START===
SCENE_NUMBER: 2
TITLE: Skyscraper Canyon
DURATION_SECONDS: 8
VISUAL: Wide tracking shot of the athlete sprinting between neon towers.
SHOT_TYPE: Wide Establishing Tracking
LENS: 24mm Anamorphic
ANGLE: Low-Angle Dramatic
LIGHTING: Volumetric Blue Laser
MOVEMENT: Dolly Forward
NARRATION: The city belongs to those who dare.
AUDIO_SFX: [SFX: Heavy rhythmic breathing and synth build-up]
IMAGE_PROMPT: Wide shot of athlete running between towering skyscrapers, neon lights, volumetric haze, cinematic 8k --ar 16:9
===SCENE_END===
AI;

    SceneCraftAgent::fake([
        $mockOutput,
    ]);

    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('scenecraft-canvas')
        ->set('conceptPrompt', 'Iklan sepatu lari futuristic 30 detik di kota malam')
        ->set('targetDuration', 14)
        ->call('generateStoryboard')
        ->assertHasNoErrors()
        ->assertSet('errorMessage', '');

    $scenes = $component->get('scenes');
    expect($scenes)->toHaveCount(2);
    expect($scenes[0]['title'])->toBe('Neon Rain Sprint');
    expect($scenes[0]['shot_type'])->toBe('Extreme Close-Up');
    expect($scenes[0]['duration'])->toBe(6);
    expect($scenes[0]['timecode'])->toBe('00:00 - 00:06');
    expect($scenes[1]['title'])->toBe('Skyscraper Canyon');
    expect($scenes[1]['timecode'])->toBe('00:06 - 00:14');
});

test('user can reorder scenes via drag and drop array', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('scenecraft-canvas');

    $initialScenes = $component->get('scenes');
    expect(count($initialScenes))->toBeGreaterThanOrEqual(2);

    $id0 = $initialScenes[0]['id'];
    $id1 = $initialScenes[1]['id'];

    // Reverse the order of first two
    $newOrder = array_column($initialScenes, 'id');
    $newOrder[0] = $id1;
    $newOrder[1] = $id0;

    $component->call('reorderScenes', $newOrder);

    $reordered = $component->get('scenes');
    expect($reordered[0]['id'])->toBe($id1);
    expect($reordered[0]['scene_number'])->toBe(1);
    expect($reordered[1]['id'])->toBe($id0);
    expect($reordered[1]['scene_number'])->toBe(2);
});

test('user can move scenes up and down', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('scenecraft-canvas');

    $initialScenes = $component->get('scenes');
    $scene1Title = $initialScenes[0]['title'];
    $scene2Title = $initialScenes[1]['title'];

    // Move scene 0 down
    $component->call('moveScene', 0, 'down');

    $updatedScenes = $component->get('scenes');
    expect($updatedScenes[0]['title'])->toBe($scene2Title);
    expect($updatedScenes[1]['title'])->toBe($scene1Title);

    // Move scene 1 up
    $component->call('moveScene', 1, 'up');

    $revertedScenes = $component->get('scenes');
    expect($revertedScenes[0]['title'])->toBe($scene1Title);
    expect($revertedScenes[1]['title'])->toBe($scene2Title);
});

test('user can add, duplicate, and delete scenes', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('scenecraft-canvas');

    $initialCount = count($component->get('scenes'));

    // Add scene
    $component->call('addScene');
    expect(count($component->get('scenes')))->toBe($initialCount + 1);

    // Duplicate scene
    $component->call('duplicateScene', 0);
    expect(count($component->get('scenes')))->toBe($initialCount + 2);

    // Delete scene
    $component->call('deleteScene', 0);
    expect(count($component->get('scenes')))->toBe($initialCount + 1);
});

test('user can refine a single scene with ai', function () {
    $mockRefined = <<<'AI'
===SCENE_START===
SCENE_NUMBER: 1
TITLE: Sunset Horizon Sprint
DURATION_SECONDS: 5
VISUAL: Golden hour close-up with warm lens flare and dust particles.
SHOT_TYPE: Macro Close-Up
LENS: 85mm f/1.2
ANGLE: Low-Angle Hero
LIGHTING: Golden Hour Rim Light
MOVEMENT: Slow Orbit
NARRATION: Feel the warmth of the run.
AUDIO_SFX: [SFX: Warm synth chord and soft wind]
IMAGE_PROMPT: Macro close-up of runner in golden hour, 85mm f1.2, photorealistic --ar 16:9
===SCENE_END===
AI;

    SceneCraftAgent::fake([
        $mockRefined,
    ]);

    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('scenecraft-canvas')
        ->call('openRefineModal', 0)
        ->assertSet('showRefineModal', true)
        ->assertSet('refiningSceneIndex', 0)
        ->set('refineInstruction', 'Ubah jadi golden hour dengan lensa 85mm')
        ->call('refineSingleScene')
        ->assertHasNoErrors()
        ->assertSet('showRefineModal', false);

    $scenes = $component->get('scenes');
    expect($scenes[0]['title'])->toBe('Sunset Horizon Sprint');
    expect($scenes[0]['lighting'])->toBe('Golden Hour Rim Light');
});

test('notion markdown and plain script exports contain scene details', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('scenecraft-canvas');

    $notionMarkdown = $component->get('notionMarkdown');
    $plainScript = $component->get('plainScript');

    expect($notionMarkdown)->toContain('# 🎬 Storyboard:');
    expect($notionMarkdown)->toContain('Scene 01:');
    expect($notionMarkdown)->toContain('/imagine prompt:');

    expect($plainScript)->toContain('PROJECT:');
    expect($plainScript)->toContain('SCENE 01');
    expect($plainScript)->toContain('VOICEOVER:');
});
