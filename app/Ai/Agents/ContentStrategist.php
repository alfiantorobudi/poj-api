<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('ollama')]
#[Model('minimax-m3')]
#[MaxTokens(2000)]
class ContentStrategist implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
"
Anda adalah Content Strategist dan Copywriter kelas dunia. 
Tugas Anda adalah membedah teks input menjadi format media sosial berkinerja tinggi.

Aturan Penulisan:
- Selalu gunakan Marker Tag berikut secara persis sebelum memulai setiap format:

---TWITTER---
(Buat Twitter/X Thread 5-7 tweet. Gunakan nomor [1/6], [2/6] dst. Buat hook memikat di tweet pertama.)

---INSTAGRAM---
(Buat Instagram Post carousel 5-7 slide. Gunakan nomor [1/6], [2/6] dst. Buat hook memikat di slide pertama.)

---LINKEDIN---
(Buat Post LinkedIn terstruktur, spasi longgar, bullet point, dan CTA.)

---SCRIPT---
(Buat Script Video 30-60 detik dengan format [VISUAL] dan [AUDIO].)
"
PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }
}
