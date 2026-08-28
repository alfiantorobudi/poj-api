<?php

use App\Ai\Agents\SceneCraftAgent;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    // Brief Inputs
    public string $conceptPrompt = '';
    public int $targetDuration = 30; // seconds
    public string $visualStyle = 'Cinematic 35mm Film';
    public string $aspectRatio = '16:9';
    public string $pacing = 'Fast & Energetic';
    public string $language = 'Indonesian';

    /** @var array<int, array<string, mixed>> */
    public array $scenes = [];

    // UI & Generation State
    public bool $isGenerating = false;
    public string $errorMessage = '';
    public string $successMessage = '';
    public ?string $conversationId = null;
    public string $activeView = 'canvas'; // 'canvas' | 'timeline' | 'script'
    public bool $showGridOverlay = false;

    // Single Scene AI Refinement State
    public bool $showRefineModal = false;
    public ?int $refiningSceneIndex = null;
    public string $refineInstruction = '';
    public bool $isRefining = false;

    // Export Modal State
    public bool $showExportModal = false;
    public string $exportType = 'notion'; // 'notion' | 'pdf' | 'script' | 'json'

    public function mount(): void
    {
        if (empty($this->conceptPrompt)) {
            $this->loadSample('running_shoes');
        }
    }

    /**
     * Load preset sample concepts
     */
    public function loadSample(string $preset = 'running_shoes'): void
    {
        if ($preset === 'running_shoes') {
            $this->conceptPrompt = 'Iklan sepatu lari futuristik "AeroPulse X". Konsep: Pelari berlari menembus hujan malam di kota metropolitan futuristik bercahaya neon. Sepatu bercahaya di setiap pijakan, memantulkan air di aspal, mempercepat waktu dan melesat melintasi garis finish.';
            $this->targetDuration = 30;
            $this->visualStyle = 'Cyberpunk Neon';
            $this->aspectRatio = '16:9';
            $this->pacing = 'Fast & Energetic';
        } elseif ($preset === 'coffee_brand') {
            $this->conceptPrompt = 'Teaser video peluncuran artisan coffee blend "Astral Roast". Konsep: Dari ekstraksi tetesan espresso makro sinematik yang lambat dan hangat, aroma uap mengepul, hingga cangkir pertama yang dinikmati seorang kreator diiringi cahaya fajar kota.';
            $this->targetDuration = 15;
            $this->visualStyle = 'Cinematic 35mm Film';
            $this->aspectRatio = '9:16';
            $this->pacing = 'Slow & Atmospheric';
        } elseif ($preset === 'scifi_trailer') {
            $this->conceptPrompt = 'Trailer film sci-fi "Nexus Horizon". Konsep: Sebuah stasiun luar angkasa raksasa kehilangan kontak. Tim ekspedisi mendarat di hangar yang sunyi, mendeteksi sinyal aneh dari reaktor inti sebelum anomali gravitasi terjadi.';
            $this->targetDuration = 60;
            $this->visualStyle = 'Sci-Fi Futuristic';
            $this->aspectRatio = '2.39:1';
            $this->pacing = 'Balanced Cinematic';
        }

        // Provide default sample scenes if empty
        if (empty($this->scenes)) {
            $this->scenes = $this->getDefaultSampleScenes();
            $this->recalculateTimecodes();
        }
    }

    /**
     * Generate full storyboard scene breakdown using SceneCraftAgent
     */
    public function generateStoryboard(): void
    {
        $this->validate([
            'conceptPrompt' => 'required|string|min:10',
            'targetDuration' => 'required|integer|min:5|max:180',
            'visualStyle' => 'required|string',
            'aspectRatio' => 'required|string',
            'pacing' => 'required|string',
        ], [
            'conceptPrompt.required' => 'Masukkan ide atau konsep video.',
            'conceptPrompt.min' => 'Ide video minimal 10 karakter.',
        ]);

        $this->isGenerating = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $agent = new SceneCraftAgent;

            if ($user = auth()->user()) {
                if ($this->conversationId) {
                    $agent = $agent->continue($this->conversationId, as: $user);
                } else {
                    $agent = $agent->forUser($user);
                }
            }

            $userPrompt = "Generate a complete, scene-by-scene visual storyboard and shooting script based on this brief:
- Video Concept: {$this->conceptPrompt}
- Target Total Duration: {$this->targetDuration} seconds
- Visual Style / Mood: {$this->visualStyle}
- Aspect Ratio: {$this->aspectRatio}
- Editing Pacing: {$this->pacing}
- Language: {$this->language}

Break down the story logically so that the sum of scene durations equals approximately {$this->targetDuration} seconds.
Follow the exact ===SCENE_START=== to ===SCENE_END=== schema with all camera specs, narration, SFX cues, and Midjourney image prompts.";

            $response = $agent->prompt($userPrompt);
            $responseText = (string) $response;

            if (isset($response->conversationId)) {
                $this->conversationId = $response->conversationId;
            }

            $parsedScenes = $this->parseScenesFromText($responseText);

            if (empty($parsedScenes)) {
                throw new \RuntimeException('AI did not return any parseable scenes. Please try again.');
            }

            $this->scenes = $parsedScenes;
            $this->recalculateTimecodes();
            $this->successMessage = 'Storyboard berhasil dibuat dengan ' . count($this->scenes) . ' scene!';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal membuat storyboard: ' . $e->getMessage();
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Parse raw AI output into structured scenes array
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseScenesFromText(string $rawText): array
    {
        $scenes = [];
        $pattern = '/===SCENE_START===(.*?)===SCENE_END===/s';

        if (preg_match_all($pattern, $rawText, $matches)) {
            foreach ($matches[1] as $index => $block) {
                $sceneNumber = $this->extractField($block, 'SCENE_NUMBER') ?: ($index + 1);
                $title = $this->extractField($block, 'TITLE') ?: "Scene {$sceneNumber}";
                $duration = (int) ($this->extractField($block, 'DURATION_SECONDS') ?: 5);
                $visual = $this->extractField($block, 'VISUAL') ?: 'Visual scene description...';
                $shotType = $this->extractField($block, 'SHOT_TYPE') ?: 'Medium Shot';
                $lens = $this->extractField($block, 'LENS') ?: '35mm';
                $angle = $this->extractField($block, 'ANGLE') ?: 'Eye-Level';
                $lighting = $this->extractField($block, 'LIGHTING') ?: 'Cinematic Natural';
                $movement = $this->extractField($block, 'MOVEMENT') ?: 'Static';
                $narration = $this->extractField($block, 'NARRATION') ?: '';
                $audioSfx = $this->extractField($block, 'AUDIO_SFX') ?: '';
                $imagePrompt = $this->extractField($block, 'IMAGE_PROMPT') ?: $visual;

                $scenes[] = [
                    'id' => (string) Str::uuid(),
                    'scene_number' => (int) $sceneNumber,
                    'title' => trim($title),
                    'duration' => max(1, $duration),
                    'timecode' => '00:00 - 00:05',
                    'visual' => trim($visual),
                    'shot_type' => trim($shotType),
                    'lens' => trim($lens),
                    'angle' => trim($angle),
                    'lighting' => trim($lighting),
                    'movement' => trim($movement),
                    'narration' => trim($narration),
                    'audio_sfx' => trim($audioSfx),
                    'image_prompt' => trim($imagePrompt),
                    'is_editing' => false,
                ];
            }
        }

        // Fallback: If no markers were found, synthesize scenes from paragraphs
        if (empty($scenes) && filled(trim($rawText))) {
            $paragraphs = array_values(array_filter(explode("\n\n", trim($rawText))));
            $sceneDuration = max(3, (int) round($this->targetDuration / max(1, count($paragraphs))));

            foreach ($paragraphs as $idx => $para) {
                $scenes[] = [
                    'id' => (string) Str::uuid(),
                    'scene_number' => $idx + 1,
                    'title' => "Scene " . ($idx + 1),
                    'duration' => $sceneDuration,
                    'timecode' => '00:00 - 00:05',
                    'visual' => trim($para),
                    'shot_type' => 'Medium Tracking Shot',
                    'lens' => '35mm Anamorphic',
                    'angle' => 'Eye-Level',
                    'lighting' => $this->visualStyle,
                    'movement' => 'Dolly Forward',
                    'narration' => trim($para),
                    'audio_sfx' => '[SFX: Cinematic ambient synth]',
                    'image_prompt' => trim($para) . ", {$this->visualStyle}, cinematic lighting, 8k, photorealistic --ar " . ($this->aspectRatio === '9:16' ? '9:16' : '16:9'),
                    'is_editing' => false,
                ];
            }
        }

        return $scenes;
    }

    /**
     * Helper to extract a field by key from block text
     */
    private function extractField(string $block, string $key): ?string
    {
        if (preg_match('/' . preg_quote($key, '/') . ':\s*(.+?)(?=\n[A-Z_]+:|$)/s', $block, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    /**
     * Recalculate timecodes, sequence numbers, and total duration
     */
    public function recalculateTimecodes(): void
    {
        $currentSeconds = 0;

        foreach ($this->scenes as $index => &$scene) {
            $scene['scene_number'] = $index + 1;
            $duration = (int) ($scene['duration'] ?? 5);

            $startMin = floor($currentSeconds / 60);
            $startSec = $currentSeconds % 60;
            $endSeconds = $currentSeconds + $duration;
            $endMin = floor($endSeconds / 60);
            $endSec = $endSeconds % 60;

            $scene['timecode'] = sprintf('%02d:%02d - %02d:%02d', $startMin, $startSec, $endMin, $endSec);
            $currentSeconds = $endSeconds;
        }
        unset($scene);
    }

    /**
     * Reorder scenes via Drag & Drop ID array
     *
     * @param array<int, string> $orderedIds
     */
    public function reorderScenes(array $orderedIds): void
    {
        $reordered = [];
        $sceneMap = collect($this->scenes)->keyBy('id');

        foreach ($orderedIds as $id) {
            if ($sceneMap->has($id)) {
                $reordered[] = $sceneMap->get($id);
            }
        }

        if (!empty($reordered)) {
            $this->scenes = $reordered;
            $this->recalculateTimecodes();
        }
    }

    /**
     * Move scene up or down in order
     */
    public function moveScene(int $index, string $direction): void
    {
        if ($direction === 'up' && $index > 0) {
            $temp = $this->scenes[$index - 1];
            $this->scenes[$index - 1] = $this->scenes[$index];
            $this->scenes[$index] = $temp;
        } elseif ($direction === 'down' && $index < count($this->scenes) - 1) {
            $temp = $this->scenes[$index + 1];
            $this->scenes[$index + 1] = $this->scenes[$index];
            $this->scenes[$index] = $temp;
        }

        $this->recalculateTimecodes();
    }

    /**
     * Add a new blank scene after specific index
     */
    public function addScene(?int $afterIndex = null): void
    {
        $newScene = [
            'id' => (string) Str::uuid(),
            'scene_number' => count($this->scenes) + 1,
            'title' => 'New Scene ' . (count($this->scenes) + 1),
            'duration' => 5,
            'timecode' => '00:00 - 00:05',
            'visual' => 'Describe what happens visually...',
            'shot_type' => 'Medium Shot',
            'lens' => '35mm',
            'angle' => 'Eye-Level',
            'lighting' => 'Cinematic Natural',
            'movement' => 'Slow Push-in',
            'narration' => 'Voiceover or dialogue script...',
            'audio_sfx' => '[SFX: Atmospheric ambient track]',
            'image_prompt' => "Cinematic film still, {$this->visualStyle}, 8k, photorealistic --ar " . ($this->aspectRatio === '9:16' ? '9:16' : '16:9'),
            'is_editing' => true,
        ];

        if ($afterIndex !== null && isset($this->scenes[$afterIndex])) {
            array_splice($this->scenes, $afterIndex + 1, 0, [$newScene]);
        } else {
            $this->scenes[] = $newScene;
        }

        $this->recalculateTimecodes();
    }

    /**
     * Duplicate a scene
     */
    public function duplicateScene(int $index): void
    {
        if (isset($this->scenes[$index])) {
            $duplicated = $this->scenes[$index];
            $duplicated['id'] = (string) Str::uuid();
            $duplicated['title'] .= ' (Copy)';
            $duplicated['is_editing'] = false;

            array_splice($this->scenes, $index + 1, 0, [$duplicated]);
            $this->recalculateTimecodes();
        }
    }

    /**
     * Delete a scene
     */
    public function deleteScene(int $index): void
    {
        if (isset($this->scenes[$index])) {
            array_splice($this->scenes, $index, 1);
            $this->recalculateTimecodes();
        }
    }

    /**
     * Toggle inline edit mode for a scene
     */
    public function toggleEditScene(int $index): void
    {
        if (isset($this->scenes[$index])) {
            $this->scenes[$index]['is_editing'] = ! ($this->scenes[$index]['is_editing'] ?? false);
            $this->recalculateTimecodes();
        }
    }

    /**
     * Open single-scene AI refinement modal
     */
    public function openRefineModal(int $index): void
    {
        if (isset($this->scenes[$index])) {
            $this->refiningSceneIndex = $index;
            $this->refineInstruction = '';
            $this->showRefineModal = true;
        }
    }

    /**
     * Close refinement modal
     */
    public function closeRefineModal(): void
    {
        $this->showRefineModal = false;
        $this->refiningSceneIndex = null;
        $this->refineInstruction = '';
    }

    /**
     * Execute AI refinement on a single scene
     */
    public function refineSingleScene(): void
    {
        if ($this->refiningSceneIndex === null || !isset($this->scenes[$this->refiningSceneIndex])) {
            return;
        }

        $this->validate([
            'refineInstruction' => 'required|string|min:4',
        ], [
            'refineInstruction.required' => 'Tuliskan instruksi perbaikan untuk scene ini.',
        ]);

        $this->isRefining = true;

        try {
            $scene = $this->scenes[$this->refiningSceneIndex];
            $agent = new SceneCraftAgent;

            if ($user = auth()->user()) {
                if ($this->conversationId) {
                    $agent = $agent->continue($this->conversationId, as: $user);
                } else {
                    $agent = $agent->forUser($user);
                }
            }

            $refinePrompt = "Refine the following single scene according to this user request: \"{$this->refineInstruction}\".
Current Scene Details:
- Title: {$scene['title']}
- Duration: {$scene['duration']}s
- Visual: {$scene['visual']}
- Shot Type: {$scene['shot_type']}
- Lens: {$scene['lens']}
- Angle: {$scene['angle']}
- Lighting: {$scene['lighting']}
- Movement: {$scene['movement']}
- Narration: {$scene['narration']}
- Audio SFX: {$scene['audio_sfx']}
- Image Prompt: {$scene['image_prompt']}

Return the refined scene strictly wrapped in a single ===SCENE_START=== to ===SCENE_END=== block matching the schema.";

            $response = $agent->prompt($refinePrompt);
            $parsed = $this->parseScenesFromText((string) $response);

            if (!empty($parsed)) {
                $refinedScene = $parsed[0];
                $refinedScene['id'] = $scene['id'];
                $refinedScene['scene_number'] = $scene['scene_number'];
                $this->scenes[$this->refiningSceneIndex] = $refinedScene;
                $this->recalculateTimecodes();
                $this->successMessage = "Scene #{$scene['scene_number']} berhasil diperbarui dengan AI!";
                $this->closeRefineModal();
            } else {
                throw new \RuntimeException('Gagal memproses hasil perbaikan AI.');
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal memperbarui scene: ' . $e->getMessage();
        } finally {
            $this->isRefining = false;
        }
    }

    /**
     * Compute total duration of all scenes combined
     */
    public function getTotalDurationProperty(): int
    {
        return array_sum(array_column($this->scenes, 'duration'));
    }

    /**
     * Compute total word count in narration
     */
    public function getTotalWordCountProperty(): int
    {
        $text = implode(' ', array_column($this->scenes, 'narration'));
        return str_word_count($text);
    }

    /**
     * Generate Notion-optimized Markdown
     */
    public function getNotionMarkdownProperty(): string
    {
        $out = "# 🎬 Storyboard: " . ($this->conceptPrompt ? Str::limit($this->conceptPrompt, 60) : 'SceneCraft Production Board') . "\n\n";
        $out .= "> 💡 **Project Specs**: {$this->visualStyle} | Aspect Ratio: {$this->aspectRatio} | Pacing: {$this->pacing} | Total Runtime: {$this->totalDuration}s\n\n";
        $out .= "## 📋 Executive Overview\n";
        $out .= "**Brief Concept:**\n" . $this->conceptPrompt . "\n\n";
        $out .= "---\n\n";
        $out .= "## 🎥 Scene-by-Scene Shot List\n\n";

        foreach ($this->scenes as $s) {
            $out .= "### Scene " . sprintf('%02d', $s['scene_number']) . ": " . $s['title'] . " `[" . $s['timecode'] . "]`\n\n";
            $out .= "| Camera Spec | Value |\n";
            $out .= "| :--- | :--- |\n";
            $out .= "| **Shot Type** | " . $s['shot_type'] . " |\n";
            $out .= "| **Lens** | " . $s['lens'] . " |\n";
            $out .= "| **Angle & Lighting** | " . $s['angle'] . " • " . $s['lighting'] . " |\n";
            $out .= "| **Camera Movement** | " . $s['movement'] . " |\n\n";

            $out .= "**👁️ Visual Action:**\n";
            $out .= "> " . $s['visual'] . "\n\n";

            if (filled($s['narration'])) {
                $out .= "**🎙️ Voiceover / Narration:**\n";
                $out .= "```text\n" . $s['narration'] . "\n```\n\n";
            }

            if (filled($s['audio_sfx'])) {
                $out .= "**🔊 Audio & SFX:** `" . $s['audio_sfx'] . "`\n\n";
            }

            $out .= "**🎨 Midjourney / DALL-E Prompt:**\n";
            $out .= "```bash\n/imagine prompt: " . $s['image_prompt'] . "\n```\n\n";
            $out .= "---\n\n";
        }

        return $out;
    }

    /**
     * Generate plain script for voice talent
     */
    public function getPlainScriptProperty(): string
    {
        $out = "PROJECT: " . Str::limit($this->conceptPrompt, 50) . "\n";
        $out .= "TARGET DURATION: {$this->totalDuration} SECONDS\n";
        $out .= "========================================\n\n";

        foreach ($this->scenes as $s) {
            $out .= "SCENE " . sprintf('%02d', $s['scene_number']) . " [" . $s['timecode'] . "] - " . strtoupper($s['title']) . "\n";
            $out .= "VISUAL: " . $s['visual'] . "\n";
            $out .= "AUDIO SFX: " . $s['audio_sfx'] . "\n";
            $out .= "VOICEOVER: " . ($s['narration'] ?: '[NO DIALOGUE]') . "\n";
            $out .= "----------------------------------------\n\n";
        }

        return $out;
    }

    /**
     * Default initial sample scenes
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDefaultSampleScenes(): array
    {
        return [
            [
                'id' => (string) Str::uuid(),
                'scene_number' => 1,
                'title' => 'Neon Puddle Ignition',
                'duration' => 5,
                'timecode' => '00:00 - 00:05',
                'visual' => 'Extreme close-up on asphalt wet with neon-reflected rain. A sleek carbon-fiber sneaker stomps into the puddle, sending luminescent droplets flying as micro-LEDs ignite along the sole.',
                'shot_type' => 'Extreme Close-Up (ECU)',
                'lens' => '85mm f/1.4 Macro',
                'angle' => 'Ground-Level Low Angle',
                'lighting' => 'Moody Cyberpunk Neon Cyan & Magenta',
                'movement' => 'High-Speed Slow Motion (120fps)',
                'narration' => 'Gravity was made to be challenged.',
                'audio_sfx' => '[SFX: Heavy water splash with bass sub-drop and electric charging hum]',
                'image_prompt' => 'Cinematic ground-level close-up of a futuristic carbon-fiber running shoe stepping into a wet city street puddle, neon magenta and teal reflections, glowing sole lights, high-speed water splash particles frozen in air, 35mm film still, octane render, 8k --ar 16:9',
                'is_editing' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'scene_number' => 2,
                'title' => 'Sprint Through the Hologram Alley',
                'duration' => 6,
                'timecode' => '00:05 - 00:11',
                'visual' => 'Medium tracking shot following the runner accelerating down a towering skyscraper canyon. Holographic billboards illuminate the athlete\'s focused eyes and aerodynamic silhouette.',
                'shot_type' => 'Medium Tracking Shot',
                'lens' => '35mm Anamorphic',
                'angle' => 'Low-Angle Dynamic',
                'lighting' => 'Volumetric Fog with Blue Laser Rim Light',
                'movement' => 'Fast Forward Tracking Dolly',
                'narration' => 'When the city sleeps, speed awakens.',
                'audio_sfx' => '[SFX: Rhythmic fast breathing, futuristic sonic boom build-up, synthwave beat drop]',
                'image_prompt' => 'Dynamic side-tracking shot of an athletic runner sprinting full speed through a foggy futuristic metropolis alley, towering skyscrapers with giant holographic advertisements, atmospheric volumetric lighting, cinematic anamorphic lens flare, photorealistic --ar 16:9',
                'is_editing' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'scene_number' => 3,
                'title' => 'The Anti-Gravity Leap',
                'duration' => 7,
                'timecode' => '00:11 - 00:18',
                'visual' => 'Low-angle hero shot of the runner leaping over a rooftop gap between two skyscrapers. Rain streams past in bullet-time as the city lights shimmer below.',
                'shot_type' => 'Wide Hero Angle',
                'lens' => '24mm Ultra-Wide',
                'angle' => 'Extreme Upward Low-Angle',
                'lighting' => 'High-Contrast Backlit Golden Horizon Glow',
                'movement' => '360 Bullet-time Orbital Pan',
                'narration' => 'Feel the pulse. Outrun your limits.',
                'audio_sfx' => '[SFX: Sound of air whooshing, heartbeat pulse, sudden brief silence before explosive synth chord]',
                'image_prompt' => 'Wide angle cinematic shot of a silhouetted athlete leaping across a rooftop gap between skyscrapers at dusk, futuristic skyline below, rain droplets suspended in air, golden hour glow mixed with neon city lights, dramatic composition, IMAX quality --ar 16:9',
                'is_editing' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'scene_number' => 4,
                'title' => 'Hero Landing & Logo Reveal',
                'duration' => 6,
                'timecode' => '00:18 - 00:24',
                'visual' => 'The runner lands firmly on the rooftop helipad with smoke venting from the shoe cushions. The camera pans up to reveal the glowing product title: AEROPULSE X.',
                'shot_type' => 'Low-Angle Medium Close-Up',
                'lens' => '50mm Prime',
                'angle' => 'Dutch Tilt Hero Angle',
                'lighting' => 'Clean Studio Edge-Lit Neon',
                'movement' => 'Slow Tilt-Up with Smooth Gimbal Lock',
                'narration' => 'AeroPulse X. Run beyond tomorrow.',
                'audio_sfx' => '[SFX: Hydraulic hiss, metallic echo, triumphant cinematic synthesizer chord]',
                'image_prompt' => 'Hero shot of futuristic runner in high-tech athletic gear catching breath on a high-tech helipad, glowing shoe sole cooling down with mist, city panorama background at night, 3D holographic title AEROPULSE X floating in air, commercial grade cinematography --ar 16:9',
                'is_editing' => false,
            ],
        ];
    }
};

?>

<div class="w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-8" 
     x-data="{
         draggedIndex: null,
         copiedId: null,
         copyToClipboard(text, id) {
             navigator.clipboard.writeText(text);
             this.copiedId = id;
             setTimeout(() => { this.copiedId = null; }, 2000);
         }
     }">

    <!-- Top Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-900 via-neutral-900 to-indigo-950/80 border border-zinc-800 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-16 -top-16 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-80 h-80 bg-violet-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                        SceneCraft Studio
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Ollama • Minimax-m3 Ready
                    </span>
                    <span class="text-xs text-zinc-400">Visual Storyboard & Shot Co-Pilot</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                    SceneCraft <span class="bg-gradient-to-r from-indigo-400 via-violet-300 to-amber-300 bg-clip-text text-transparent">Story & Shot Director</span>
                </h1>
                <p class="text-sm sm:text-base text-zinc-300 max-w-2xl">
                    Ubah ide video mentah menjadi breakdown scene sinematik, shot list kamera (lensa, angle, lighting), narasi, dan prompt siap pakai untuk Midjourney/Flux.
                </p>
            </div>

            <!-- Quick Action Bar -->
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" 
                        wire:click="$set('showExportModal', true)"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-zinc-200 bg-zinc-800/90 hover:bg-zinc-700/90 border border-zinc-700 shadow-sm transition duration-150">
                    <svg class="size-4 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Export Storyboard
                </button>
                <button type="button" 
                        onclick="window.print()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-zinc-200 bg-zinc-800/90 hover:bg-zinc-700/90 border border-zinc-700 shadow-sm transition duration-150">
                    <svg class="size-4 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print PDF Sheet
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if($errorMessage)
        <div class="p-4 rounded-2xl bg-red-950/60 border border-red-800/80 text-red-200 text-sm flex items-start gap-3 shadow-lg animate-fadeIn">
            <svg class="size-5 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div class="flex-1">
                <p class="font-semibold text-red-200">Terjadi Kesalahan</p>
                <p class="mt-0.5 text-red-300 text-xs sm:text-sm">{{ $errorMessage }}</p>
            </div>
            <button type="button" wire:click="$set('errorMessage', '')" class="text-red-400 hover:text-red-200">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
    @endif

    @if($successMessage)
        <div class="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-800/80 text-emerald-200 text-sm flex items-start gap-3 shadow-lg animate-fadeIn">
            <svg class="size-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div class="flex-1">
                <p class="font-semibold text-emerald-200">Berhasil!</p>
                <p class="mt-0.5 text-emerald-300 text-xs sm:text-sm">{{ $successMessage }}</p>
            </div>
            <button type="button" wire:click="$set('successMessage', '')" class="text-emerald-400 hover:text-emerald-200">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
    @endif

    <!-- Briefing & Director Control Studio -->
    <div class="bg-zinc-900/90 border border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-800 pb-4">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polygon points="10 8 16 12 10 16 10 8"></polygon>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white">Video Brief & Directing Controls</h2>
                    <p class="text-xs text-zinc-400">Tentukan konsep, durasi, aspek rasio, dan mood visual</p>
                </div>
            </div>

            <!-- Sample Preset Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs text-zinc-500 font-medium">Contoh Brief:</span>
                <button type="button" wire:click="loadSample('running_shoes')" 
                        class="px-2.5 py-1 text-xs font-medium rounded-lg bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white border border-zinc-700 transition">
                    👟 Sepatu 30s
                </button>
                <button type="button" wire:click="loadSample('coffee_brand')" 
                        class="px-2.5 py-1 text-xs font-medium rounded-lg bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white border border-zinc-700 transition">
                    ☕ Coffee 15s (9:16)
                </button>
                <button type="button" wire:click="loadSample('scifi_trailer')" 
                        class="px-2.5 py-1 text-xs font-medium rounded-lg bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white border border-zinc-700 transition">
                    🚀 Sci-Fi 60s
                </button>
            </div>
        </div>

        <!-- Concept Textarea -->
        <div class="space-y-2">
            <label for="conceptPrompt" class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider">
                Konsep / Ide Cerita Video <span class="text-red-400">*</span>
            </label>
            <div class="relative">
                <textarea id="conceptPrompt" wire:model="conceptPrompt" rows="3"
                          placeholder="Masukkan ide video Anda (misal: 'Iklan smartwatch tahan air 30 detik. Pria menyelam di laut dalam berkarang, jam menyala menunjukkan kedalaman dan detak jantung...')"
                          class="w-full px-4 py-3 rounded-2xl bg-zinc-950/80 border border-zinc-700 text-white placeholder-zinc-500 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
            </div>
            @error('conceptPrompt') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
        </div>

        <!-- Configuration Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
            <!-- Target Duration -->
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-zinc-300">Target Durasi</label>
                <select wire:model="targetDuration" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-700 text-white text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="15">15 Detik (Story / Reel)</option>
                    <option value="30">30 Detik (Commercial Standar)</option>
                    <option value="60">60 Detik (Extended Ad / Trailer)</option>
                    <option value="90">90 Detik (Brand Film)</option>
                    <option value="120">120 Detik (Short Film)</option>
                </select>
            </div>

            <!-- Visual Style -->
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-zinc-300">Visual Style & Mood</label>
                <select wire:model="visualStyle" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-700 text-white text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="Cyberpunk Neon">Cyberpunk Neon</option>
                    <option value="Cinematic 35mm Film">Cinematic 35mm Film</option>
                    <option value="Minimalist Commercial">Minimalist Modern Commercial</option>
                    <option value="Sci-Fi Futuristic">Sci-Fi Futuristic</option>
                    <option value="Anime Concept Art">Anime / Stylized Concept Art</option>
                    <option value="Gritty Documentary">Gritty Documentary</option>
                    <option value="Luxury Golden Hour">Luxury Golden Hour</option>
                </select>
            </div>

            <!-- Aspect Ratio -->
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-zinc-300">Aspek Rasio (Kamera)</label>
                <select wire:model="aspectRatio" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-700 text-white text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="16:9">16:9 (Landscape / YouTube)</option>
                    <option value="9:16">9:16 (Vertical / Reels / TikTok)</option>
                    <option value="1:1">1:1 (Square / Feed)</option>
                    <option value="2.39:1">2.39:1 (Anamorphic Cinema)</option>
                </select>
            </div>

            <!-- Pacing -->
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-zinc-300">Editing Pacing</label>
                <select wire:model="pacing" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-700 text-white text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="Fast & Energetic">Fast & Energetic (2-4s / shot)</option>
                    <option value="Balanced Cinematic">Balanced Cinematic (4-7s / shot)</option>
                    <option value="Slow & Atmospheric">Slow & Atmospheric (7-12s / shot)</option>
                </select>
            </div>
        </div>

        <!-- Submit Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-zinc-800">
            <div class="flex items-center gap-2 text-xs text-zinc-400">
                <svg class="size-4 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 14 14"></polyline>
                </svg>
                <span>Estimasi: <strong>{{ count($scenes) }}</strong> scene • Runtime: <strong>{{ $this->totalDuration }}s</strong></span>
            </div>

            <button type="button" wire:click="generateStoryboard" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2.5 px-6 py-3 rounded-2xl text-sm font-bold text-white bg-gradient-to-r from-indigo-600 via-indigo-500 to-violet-600 hover:from-indigo-500 hover:to-violet-500 active:scale-[0.98] shadow-lg shadow-indigo-600/30 transition duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="generateStoryboard" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
                </svg>
                <svg wire:loading wire:target="generateStoryboard" class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span wire:loading.remove wire:target="generateStoryboard">Generate Storyboard & Shots</span>
                <span wire:loading wire:target="generateStoryboard">AI Sedang Menyusun Scene...</span>
            </button>
        </div>
    </div>

    <!-- Canvas Navigation & Production Stats Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900 border border-zinc-800 rounded-2xl px-6 py-4 shadow-sm">
        <!-- View Tabs -->
        <div class="flex items-center gap-1.5 bg-zinc-950 p-1 rounded-xl border border-zinc-800">
            <button type="button" wire:click="$set('activeView', 'canvas')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeView === 'canvas' ? 'bg-indigo-600 text-white shadow' : 'text-zinc-400 hover:text-white' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Interactive Canvas
                </span>
            </button>
            <button type="button" wire:click="$set('activeView', 'timeline')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeView === 'timeline' ? 'bg-indigo-600 text-white shadow' : 'text-zinc-400 hover:text-white' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                        <line x1="4" y1="18" x2="20" y2="18"></line>
                    </svg>
                    Timeline Sheet
                </span>
            </button>
            <button type="button" wire:click="$set('activeView', 'script')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeView === 'script' ? 'bg-indigo-600 text-white shadow' : 'text-zinc-400 hover:text-white' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    Script (VO / SFX)
                </span>
            </button>
        </div>

        <!-- Production Metrics & Toggles -->
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-3 text-xs text-zinc-300">
                <span class="px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700">
                    🎬 <strong>{{ count($scenes) }}</strong> Scenes
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700">
                    ⏱️ <strong>{{ $this->totalDuration }}s</strong> / {{ $targetDuration }}s
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700">
                    🎙️ <strong>{{ $this->totalWordCount }}</strong> Kata VO
                </span>
            </div>

            <button type="button" wire:click="$toggle('showGridOverlay')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition {{ $showGridOverlay ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40' : 'bg-zinc-800 text-zinc-400 border-zinc-700 hover:text-white' }}">
                📐 Rule of Thirds
            </button>

            <button type="button" wire:click="addScene"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-300 bg-emerald-950/60 hover:bg-emerald-900/60 border border-emerald-800/80 transition">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Scene
            </button>
        </div>
    </div>

    <!-- MAIN VIEW: Interactive Canvas with Drag-and-Drop -->
    @if($activeView === 'canvas')
        <div class="space-y-6" id="storyboard-canvas">
            <div class="flex items-center justify-between text-xs text-zinc-400 px-1">
                <span>💡 <em>Drag kartu scene pada gagang <span class="font-bold text-zinc-300">⋮⋮</span> untuk mengatur ulang urutan scene. Timecode akan diperbarui otomatis.</em></span>
            </div>

            <!-- Scene Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-6">
                @forelse($scenes as $index => $scene)
                    <div wire:key="scene-card-{{ $scene['id'] }}"
                         x-data="{ isHovered: false }"
                         @mouseenter="isHovered = true"
                         @mouseleave="isHovered = false"
                         draggable="true"
                         @dragstart="draggedIndex = {{ $index }}; $event.dataTransfer.effectAllowed = 'move';"
                         @dragover.prevent="$event.dataTransfer.dropEffect = 'move'"
                         @drop.prevent="
                             if (draggedIndex !== null && draggedIndex !== {{ $index }}) {
                                 let order = Array.from(document.querySelectorAll('[data-scene-id]')).map(el => el.getAttribute('data-scene-id'));
                                 let moved = order.splice(draggedIndex, 1)[0];
                                 order.splice({{ $index }}, 0, moved);
                                 $wire.reorderScenes(order);
                             }
                             draggedIndex = null;
                         "
                         data-scene-id="{{ $scene['id'] }}"
                         class="group relative bg-zinc-900/95 border border-zinc-800 hover:border-indigo-500/50 rounded-3xl p-5 sm:p-6 shadow-xl transition duration-200 flex flex-col justify-between gap-5 {{ ($scene['is_editing'] ?? false) ? 'ring-2 ring-indigo-500' : '' }}">

                        <!-- Card Top Bar: Grip, Scene #, Timecode & Actions -->
                        <div class="flex items-center justify-between gap-3 border-b border-zinc-800/80 pb-3.5">
                            <div class="flex items-center gap-2.5">
                                <!-- Drag Grip Handle -->
                                <button type="button" class="cursor-grab active:cursor-grabbing p-1 rounded-lg text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800 transition" title="Tahan dan geser untuk reorder">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="5" r="1"></circle>
                                        <circle cx="9" cy="12" r="1"></circle>
                                        <circle cx="9" cy="19" r="1"></circle>
                                        <circle cx="15" cy="5" r="1"></circle>
                                        <circle cx="15" cy="12" r="1"></circle>
                                        <circle cx="15" cy="19" r="1"></circle>
                                    </svg>
                                </button>

                                <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-600 text-white tracking-wide">
                                    SCENE {{ sprintf('%02d', $scene['scene_number']) }}
                                </span>

                                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-medium bg-zinc-800 text-amber-300 border border-zinc-700">
                                    ⏱️ {{ $scene['timecode'] }} ({{ $scene['duration'] }}s)
                                </span>
                            </div>

                            <!-- Scene Micro Actions -->
                            <div class="flex items-center gap-1 opacity-90 group-hover:opacity-100 transition">
                                <button type="button" wire:click="openRefineModal({{ $index }})" 
                                        class="p-1.5 rounded-lg text-indigo-400 hover:bg-indigo-950/60 hover:text-indigo-300 transition" title="Refine dengan AI">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"></path>
                                    </svg>
                                </button>
                                <button type="button" wire:click="toggleEditScene({{ $index }})"
                                        class="p-1.5 rounded-lg text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition" title="Edit Manual">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button type="button" wire:click="duplicateScene({{ $index }})"
                                        class="p-1.5 rounded-lg text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition" title="Duplikasi Scene">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                                <button type="button" wire:click="deleteScene({{ $index }})"
                                        class="p-1.5 rounded-lg text-zinc-400 hover:bg-red-950/60 hover:text-red-300 transition" title="Hapus Scene">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Scene Title & Duration Editor -->
                        @if($scene['is_editing'] ?? false)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 bg-zinc-950/80 p-3 rounded-2xl border border-zinc-800">
                                <div class="sm:col-span-2">
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Judul Scene</label>
                                    <input type="text" wire:model.defer="scenes.{{ $index }}.title" 
                                           class="w-full px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Durasi (Detik)</label>
                                    <input type="number" wire:model.defer="scenes.{{ $index }}.duration" wire:change="recalculateTimecodes"
                                           min="1" max="60" class="w-full px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                            </div>
                        @else
                            <h3 class="text-base font-bold text-white tracking-tight flex items-center justify-between">
                                <span>{{ $scene['title'] }}</span>
                            </h3>
                        @endif

                        <!-- Visual Frame Preview Container (Cinematic Viewfinder) -->
                        <div class="relative overflow-hidden rounded-2xl bg-zinc-950 border border-zinc-800 flex flex-col items-center justify-center text-center p-4 min-h-[160px] group/frame">
                            <!-- Aspect Ratio Decorative Mask -->
                            <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/40 to-transparent pointer-events-none"></div>

                            <!-- Rule of Thirds Grid Overlay -->
                            @if($showGridOverlay)
                                <div class="absolute inset-0 grid grid-cols-3 grid-rows-3 pointer-events-none opacity-30 border border-indigo-500/30">
                                    <div class="border-r border-b border-indigo-500/30"></div>
                                    <div class="border-r border-b border-indigo-500/30"></div>
                                    <div class="border-b border-indigo-500/30"></div>
                                    <div class="border-r border-b border-indigo-500/30"></div>
                                    <div class="border-r border-b border-indigo-500/30"></div>
                                    <div class="border-b border-indigo-500/30"></div>
                                    <div class="border-r border-indigo-500/30"></div>
                                    <div class="border-r border-indigo-500/30"></div>
                                    <div></div>
                                </div>
                            @endif

                            <!-- Viewfinder Crosshairs -->
                            <div class="absolute top-2 left-2 size-2 border-t-2 border-l-2 border-zinc-600"></div>
                            <div class="absolute top-2 right-2 size-2 border-t-2 border-r-2 border-zinc-600"></div>
                            <div class="absolute bottom-2 left-2 size-2 border-b-2 border-l-2 border-zinc-600"></div>
                            <div class="absolute bottom-2 right-2 size-2 border-b-2 border-r-2 border-zinc-600"></div>

                            <div class="relative z-10 px-4 py-2 space-y-2">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-zinc-800/80 text-zinc-300 border border-zinc-700">
                                    🎥 Frame {{ sprintf('%02d', $scene['scene_number']) }} • {{ $aspectRatio }}
                                </span>
                                <p class="text-xs sm:text-sm text-zinc-200 font-medium line-clamp-3 italic">
                                    "{{ $scene['visual'] }}"
                                </p>
                            </div>
                        </div>

                        <!-- Camera & Directing Specs Pill Badges -->
                        @if($scene['is_editing'] ?? false)
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs bg-zinc-950 p-3 rounded-2xl border border-zinc-800">
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Shot Type</label>
                                    <input type="text" wire:model.defer="scenes.{{ $index }}.shot_type" class="w-full px-2 py-1 rounded bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Lens</label>
                                    <input type="text" wire:model.defer="scenes.{{ $index }}.lens" class="w-full px-2 py-1 rounded bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Angle</label>
                                    <input type="text" wire:model.defer="scenes.{{ $index }}.angle" class="w-full px-2 py-1 rounded bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Lighting</label>
                                    <input type="text" wire:model.defer="scenes.{{ $index }}.lighting" class="w-full px-2 py-1 rounded bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-[10px] uppercase font-bold text-zinc-400">Camera Movement</label>
                                    <input type="text" wire:model.defer="scenes.{{ $index }}.movement" class="w-full px-2 py-1 rounded bg-zinc-900 border border-zinc-700 text-white text-xs">
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-violet-500/10 text-violet-300 border border-violet-500/20">
                                    📸 {{ $scene['shot_type'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-500/10 text-blue-300 border border-blue-500/20">
                                    🔍 {{ $scene['lens'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/20">
                                    💡 {{ $scene['lighting'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                    🔄 {{ $scene['movement'] }}
                                </span>
                            </div>
                        @endif

                        <!-- Narration & Audio SFX -->
                        <div class="space-y-2 bg-zinc-950/60 p-3.5 rounded-2xl border border-zinc-800/80 text-xs">
                            <!-- Voiceover / Dialogue -->
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400 flex items-center gap-1">
                                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
                                    Narration / Voiceover:
                                </span>
                                @if($scene['is_editing'] ?? false)
                                    <textarea wire:model.defer="scenes.{{ $index }}.narration" rows="2" class="w-full mt-1 p-2 rounded-lg bg-zinc-900 border border-zinc-700 text-white text-xs"></textarea>
                                @else
                                    <p class="text-zinc-200 mt-0.5 italic">
                                        {{ filled($scene['narration']) ? '"' . $scene['narration'] . '"' : '— (Instrumental / No Dialogue)' }}
                                    </p>
                                @endif
                            </div>

                            <!-- Audio & SFX Cue -->
                            @if(filled($scene['audio_sfx']) || ($scene['is_editing'] ?? false))
                                <div class="pt-1.5 border-t border-zinc-800/50">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 flex items-center gap-1">
                                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                                        Audio & SFX:
                                    </span>
                                    @if($scene['is_editing'] ?? false)
                                        <input type="text" wire:model.defer="scenes.{{ $index }}.audio_sfx" class="w-full mt-1 px-2 py-1 rounded bg-zinc-900 border border-zinc-700 text-white text-xs">
                                    @else
                                        <p class="text-zinc-400 font-mono text-[11px] mt-0.5">
                                            {{ $scene['audio_sfx'] }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Midjourney / DALL-E Prompt Box -->
                        <div class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1">
                                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                    Midjourney / Flux Prompt
                                </span>

                                <button type="button" 
                                        @click="copyToClipboard({{ json_encode($scene['image_prompt']) }}, '{{ $scene['id'] }}')"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold transition bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">
                                    <template x-if="copiedId === '{{ $scene['id'] }}'">
                                        <span class="flex items-center gap-1 text-emerald-400">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            Tersalin!
                                        </span>
                                    </template>
                                    <template x-if="copiedId !== '{{ $scene['id'] }}'">
                                        <span class="flex items-center gap-1">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                            Copy Prompt
                                        </span>
                                    </template>
                                </button>
                            </div>

                            @if($scene['is_editing'] ?? false)
                                <textarea wire:model.defer="scenes.{{ $index }}.image_prompt" rows="2" class="w-full p-2 rounded-xl bg-zinc-950 border border-zinc-700 text-zinc-300 font-mono text-[11px]"></textarea>
                            @else
                                <div class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800/80 text-zinc-300 font-mono text-[11px] leading-relaxed break-words">
                                    {{ $scene['image_prompt'] }}
                                </div>
                            @endif
                        </div>

                        <!-- Card Footer Controls -->
                        <div class="flex items-center justify-between pt-2 border-t border-zinc-800/60 text-xs">
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="moveScene({{ $index }}, 'up')" {{ $index === 0 ? 'disabled' : '' }}
                                        class="p-1 rounded text-zinc-500 hover:text-zinc-300 disabled:opacity-30" title="Pindah ke Atas">
                                    ▲
                                </button>
                                <button type="button" wire:click="moveScene({{ $index }}, 'down')" {{ $index === count($scenes) - 1 ? 'disabled' : '' }}
                                        class="p-1 rounded text-zinc-500 hover:text-zinc-300 disabled:opacity-30" title="Pindah ke Bawah">
                                    ▼
                                </button>
                            </div>

                            @if($scene['is_editing'] ?? false)
                                <button type="button" wire:click="toggleEditScene({{ $index }})"
                                        class="px-3 py-1 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-500">
                                    Simpan Perubahan
                                </button>
                            @else
                                <button type="button" wire:click="addScene({{ $index }})"
                                        class="text-zinc-500 hover:text-indigo-400 text-[11px] font-medium flex items-center gap-1">
                                    + Sisipkan Scene Baru
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-16 text-center bg-zinc-900/50 border border-zinc-800 rounded-3xl space-y-4">
                        <div class="size-16 mx-auto rounded-full bg-zinc-800 flex items-center justify-center text-zinc-500">
                            <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-white">Belum Ada Scene</h3>
                        <p class="text-sm text-zinc-400 max-w-md mx-auto">
                            Masukkan ide video di form atas atau klik tombol Muat Contoh untuk memulai breakdown storyboard.
                        </p>
                        <button type="button" wire:click="loadSample('running_shoes')" class="px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-500">
                            Muat Contoh Storyboard
                        </button>
                    </div>
                @endforelse
            </div>
        </div>
    @elseif($activeView === 'timeline')
        <!-- TIMELINE SHEET VIEW -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-zinc-800 flex items-center justify-between">
                <h3 class="text-lg font-bold text-white">Timeline & Shot List Table</h3>
                <span class="text-xs text-zinc-400 font-mono">Total Duration: {{ $this->totalDuration }} seconds</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-zinc-300">
                    <thead class="bg-zinc-950 text-zinc-400 uppercase font-bold text-[10px] tracking-wider border-b border-zinc-800">
                        <tr>
                            <th class="p-4">#</th>
                            <th class="p-4">Timecode</th>
                            <th class="p-4">Scene & Visual Action</th>
                            <th class="p-4">Camera & Lens</th>
                            <th class="p-4">Lighting & Move</th>
                            <th class="p-4">Voiceover / Script</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($scenes as $s)
                            <tr class="hover:bg-zinc-800/50 transition">
                                <td class="p-4 font-bold text-indigo-400 font-mono">{{ sprintf('%02d', $s['scene_number']) }}</td>
                                <td class="p-4 font-mono text-amber-300">{{ $s['timecode'] }}</td>
                                <td class="p-4 max-w-xs">
                                    <p class="font-bold text-white">{{ $s['title'] }}</p>
                                    <p class="text-zinc-400 mt-1 line-clamp-2">{{ $s['visual'] }}</p>
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <span class="block font-semibold text-violet-300">{{ $s['shot_type'] }}</span>
                                    <span class="text-zinc-400">{{ $s['lens'] }} • {{ $s['angle'] }}</span>
                                </td>
                                <td class="p-4">
                                    <span class="block text-zinc-300">{{ $s['lighting'] }}</span>
                                    <span class="text-zinc-400">{{ $s['movement'] }}</span>
                                </td>
                                <td class="p-4 max-w-xs italic text-zinc-200">
                                    {{ filled($s['narration']) ? '"' . $s['narration'] . '"' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($activeView === 'script')
        <!-- SCRIPT & VOICEOVER VIEW -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-white">Shooting Script & Audio Direction</h3>
                    <p class="text-xs text-zinc-400">Format teks siap pakai untuk Voice Over artist dan sound engineer</p>
                </div>
                <button type="button" @click="copyToClipboard({{ json_encode($this->plainScript) }}, 'plain-script')"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition">
                    <span x-show="copiedId !== 'plain-script'">Copy Script Teks</span>
                    <span x-show="copiedId === 'plain-script'" class="text-emerald-300">Tersalin!</span>
                </button>
            </div>

            <div class="space-y-6 font-mono text-xs sm:text-sm bg-zinc-950 p-6 rounded-2xl border border-zinc-800 max-h-[600px] overflow-y-auto">
                @foreach($scenes as $s)
                    <div class="border-b border-zinc-800/80 pb-4 space-y-2">
                        <div class="flex items-center justify-between text-indigo-400 font-bold">
                            <span>SCENE {{ sprintf('%02d', $s['scene_number']) }}: {{ strtoupper($s['title']) }}</span>
                            <span class="text-amber-300 font-normal">[{{ $s['timecode'] }}]</span>
                        </div>
                        <p class="text-zinc-400"><strong class="text-zinc-300">VISUAL:</strong> {{ $s['visual'] }}</p>
                        <p class="text-zinc-400"><strong class="text-zinc-300">SFX:</strong> {{ $s['audio_sfx'] }}</p>
                        <p class="text-white"><strong class="text-emerald-400">VO:</strong> {{ $s['narration'] ?: '[INSTRUMENTAL / NO DIALOGUE]' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Single Scene AI Refinement Modal -->
    @if($showRefineModal && $refiningSceneIndex !== null && isset($scenes[$refiningSceneIndex]))
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
            <div class="w-full max-w-lg bg-zinc-900 border border-zinc-700 rounded-3xl p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="size-8 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"></path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-white">AI Refine: Scene #{{ sprintf('%02d', $scenes[$refiningSceneIndex]['scene_number']) }}</h4>
                            <p class="text-xs text-zinc-400">{{ $scenes[$refiningSceneIndex]['title'] }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeRefineModal" class="text-zinc-400 hover:text-white">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-zinc-300">Instruksi Perbaikan AI untuk Scene Ini</label>
                    <textarea wire:model="refineInstruction" rows="3"
                              placeholder="Misal: 'Ubah lighting menjadi sunset golden hour, lensa jadi 85mm f/1.2 macro close-up, dan tambahkan narasi yang lebih emosional...'"
                              class="w-full p-3 rounded-xl bg-zinc-950 border border-zinc-700 text-white text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                    @error('refineInstruction') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeRefineModal" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-300 hover:bg-zinc-800">
                        Batal
                    </button>
                    <button type="button" wire:click="refineSingleScene" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition disabled:opacity-50">
                        <svg wire:loading wire:target="refineSingleScene" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>Perbaiki dengan AI</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Storyboard Export Suite Modal -->
    @if($showExportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
            <div class="w-full max-w-2xl bg-zinc-900 border border-zinc-700 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Export Storyboard Document</h3>
                            <p class="text-xs text-zinc-400">Pilih format ekspor dokumen pra-produksi</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showExportModal', false)" class="text-zinc-400 hover:text-white">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>

                <!-- Format Switcher Tabs -->
                <div class="grid grid-cols-3 gap-2 bg-zinc-950 p-1.5 rounded-2xl border border-zinc-800">
                    <button type="button" wire:click="$set('exportType', 'notion')"
                            class="py-2.5 rounded-xl text-xs font-semibold transition {{ $exportType === 'notion' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-white' }}">
                        📝 Notion (Markdown)
                    </button>
                    <button type="button" wire:click="$set('exportType', 'script')"
                            class="py-2.5 rounded-xl text-xs font-semibold transition {{ $exportType === 'script' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-white' }}">
                        🎙️ Voiceover Script
                    </button>
                    <button type="button" wire:click="$set('exportType', 'json')"
                            class="py-2.5 rounded-xl text-xs font-semibold transition {{ $exportType === 'json' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-white' }}">
                        📦 Raw JSON
                    </button>
                </div>

                <!-- Export Preview Box -->
                <div class="relative bg-zinc-950 p-4 rounded-2xl border border-zinc-800 max-h-72 overflow-y-auto">
                    <pre class="text-xs font-mono text-zinc-300 whitespace-pre-wrap">@if($exportType === 'notion'){{ $this->notionMarkdown }}@elseif($exportType === 'script'){{ $this->plainScript }}@else{{ json_encode($scenes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}@endif</pre>
                </div>

                <!-- Export Actions -->
                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs text-zinc-500">Copy dan paste langsung ke Notion workspace Anda.</span>

                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="$set('showExportModal', false)" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-400 hover:text-white">
                            Tutup
                        </button>
                        <button type="button" 
                                @click="copyToClipboard(
                                    {{ json_encode($exportType === 'notion' ? $this->notionMarkdown : ($exportType === 'script' ? $this->plainScript : json_encode($scenes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) }}, 
                                    'modal-export'
                                )"
                                class="px-5 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition flex items-center gap-2">
                            <span x-show="copiedId !== 'modal-export'">Copy ke Clipboard</span>
                            <span x-show="copiedId === 'modal-export'" class="text-emerald-300">✓ Berhasil Tersalin!</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
