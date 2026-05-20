<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_send_message_to_assigned_manager(): void
    {
        $manager = User::factory()->create([
            'login' => 'manager-support',
            'email' => 'manager-support@example.com',
            'is_manager' => true,
        ]);

        $client = User::factory()->create([
            'login' => 'client-support',
            'email' => 'client-support@example.com',
            'manager_id' => $manager->id,
            'is_manager' => false,
        ]);

        $this->actingAs($client)
            ->post(route('account.support.messages.store'), [
                'message' => 'Нужна помощь по составу заказа.',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('support_messages', [
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'sender_id' => $client->id,
            'message' => 'Нужна помощь по составу заказа.',
        ]);
    }

    public function test_manager_can_reply_only_to_own_client(): void
    {
        $manager = User::factory()->create([
            'login' => 'manager-reply',
            'email' => 'manager-reply@example.com',
            'is_manager' => true,
        ]);

        $otherManager = User::factory()->create([
            'login' => 'manager-other-reply',
            'email' => 'manager-other-reply@example.com',
            'is_manager' => true,
        ]);

        $client = User::factory()->create([
            'login' => 'client-reply',
            'email' => 'client-reply@example.com',
            'manager_id' => $manager->id,
            'is_manager' => false,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.support.messages.store'), [
                'client_id' => $client->id,
                'message' => 'Принял вопрос, вернусь с ответом сегодня.',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('support_messages', [
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'sender_id' => $manager->id,
            'message' => 'Принял вопрос, вернусь с ответом сегодня.',
        ]);

        $this->actingAs($otherManager)
            ->post(route('manager.support.messages.store'), [
                'client_id' => $client->id,
                'message' => 'Попытка чужого ответа.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('support_messages', [
            'manager_id' => $otherManager->id,
            'message' => 'Попытка чужого ответа.',
        ]);
    }

    public function test_manager_chats_page_shows_only_assigned_clients(): void
    {
        $manager = User::factory()->create([
            'login' => 'manager-chat-page',
            'email' => 'manager-chat-page@example.com',
            'is_manager' => true,
        ]);

        $otherManager = User::factory()->create([
            'login' => 'manager-chat-other',
            'email' => 'manager-chat-other@example.com',
            'is_manager' => true,
        ]);

        $client = User::factory()->create([
            'name' => 'Клиент в чате',
            'login' => 'client-chat-page',
            'email' => 'client-chat-page@example.com',
            'manager_id' => $manager->id,
            'is_manager' => false,
        ]);

        User::factory()->create([
            'name' => 'Чужой клиент',
            'login' => 'client-chat-other',
            'email' => 'client-chat-other@example.com',
            'manager_id' => $otherManager->id,
            'is_manager' => false,
        ]);

        SupportMessage::query()->create([
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'sender_id' => $client->id,
            'message' => 'Нужна консультация по заказу.',
        ]);

        $this->actingAs($manager)
            ->get(route('manager.chats.index'))
            ->assertOk()
            ->assertSee('Клиент в чате')
            ->assertSee('Нужна консультация по заказу.')
            ->assertDontSee('Чужой клиент');
    }
}
