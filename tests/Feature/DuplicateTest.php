<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Members and outings must never be recorded twice: double tap, slow network, offline replay.
 */
class DuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->signInAdmin();
    }

    /** @param  array<string, mixed>  $overrides */
    private function member(array $overrides = []): array
    {
        return ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'level' => 'intermediaire', 'is_active' => '1', ...$overrides];
    }

    public function test_creation_forms_carry_a_device_uuid(): void
    {
        $this->get(route('members.create'))->assertOk()->assertSee('name="uuid" value="" data-fresh-uuid', false);
        $this->get(route('outings.create'))->assertOk()->assertSee('name="uuid" value="" data-fresh-uuid', false);
        $member = Member::factory()->for($this->user->association)->create();
        $this->get(route('members.edit', $member))->assertOk()->assertDontSee('data-fresh-uuid', false);
    }

    public function test_a_member_form_sent_twice_creates_one_member(): void
    {
        $uuid = (string) Str::uuid();

        $this->post(route('members.store'), $this->member(['uuid' => $uuid]))->assertRedirect();
        $member = Member::sole();
        $this->assertSame($uuid, $member->uuid);

        $this->post(route('members.store'), $this->member(['uuid' => $uuid]))
            ->assertRedirect(route('members.show', $member))
            ->assertSessionHas('status', 'Membre déjà enregistré');

        $this->assertSame(1, Member::count());
    }

    public function test_a_member_with_the_same_name_is_refused_unless_it_is_a_homonym(): void
    {
        Member::factory()->for($this->user->association)->create(['first_name' => 'Kévin', 'last_name' => 'Rosemain']);

        $this->post(route('members.store'), $this->member(['first_name' => ' kévin ', 'last_name' => 'ROSEMAIN', 'uuid' => (string) Str::uuid()]))
            ->assertSessionHasErrors(['first_name', 'homonym']);
        $this->assertSame(1, Member::count());

        $this->get(route('members.create'))->assertOk()->assertDontSee('Homonyme');
        $this->post(route('members.store'), $this->member(['homonym' => '1', 'uuid' => (string) Str::uuid()]))->assertSessionHasNoErrors();
        $this->assertSame(2, Member::count());
    }

    public function test_homonym_checkbox_appears_after_the_warning(): void
    {
        Member::factory()->for($this->user->association)->create(['first_name' => 'Kévin', 'last_name' => 'Rosemain']);

        $this->from(route('members.create'))->post(route('members.store'), $this->member())->assertRedirect(route('members.create'));
        $this->get(route('members.create'))->assertOk()->assertSee('Homonyme : c’est une autre personne')->assertSee('existe déjà dans les membres');
    }

    public function test_same_name_in_another_association_or_deleted_is_not_a_duplicate(): void
    {
        Member::factory()->create(['first_name' => 'Kévin', 'last_name' => 'Rosemain']);
        Member::factory()->for($this->user->association)->create(['first_name' => 'Kévin', 'last_name' => 'Rosemain'])->delete();

        $this->post(route('members.store'), $this->member())->assertSessionHasNoErrors();
        $this->assertSame(1, Member::query()->forAssociation($this->user->association_id)->count());
    }

    public function test_renaming_a_member_to_an_existing_name_is_refused_but_saving_it_unchanged_is_fine(): void
    {
        Member::factory()->for($this->user->association)->create(['first_name' => 'Kévin', 'last_name' => 'Rosemain']);
        $other = Member::factory()->for($this->user->association)->create(['first_name' => 'Ludovic', 'last_name' => 'Sainte-Rose']);

        $this->put(route('members.update', $other), $this->member())->assertSessionHasErrors('first_name');
        $this->put(route('members.update', $other), $this->member(['first_name' => 'Ludovic', 'last_name' => 'Sainte-Rose', 'nickname' => 'Ludo']))->assertSessionHasNoErrors();
        $this->assertSame('Ludo', $other->fresh()->nickname);
    }

    public function test_an_outing_form_sent_twice_creates_one_outing(): void
    {
        $uuid = (string) Str::uuid();
        $payload = ['uuid' => $uuid, 'type' => 'entrainement', 'title' => 'Entraînement', 'date' => today()->toDateString()];

        $this->post(route('outings.store'), $payload)->assertRedirect();
        $outing = Outing::sole();
        $this->post(route('outings.store'), $payload)->assertRedirect(route('outings.show', $outing));

        $this->assertSame(1, Outing::count());
    }

    public function test_a_uuid_of_another_association_is_refused(): void
    {
        $foreign = Outing::factory()->create();
        $foreignMember = Member::factory()->create();

        $this->post(route('outings.store'), ['uuid' => $foreign->uuid, 'type' => 'entrainement', 'title' => 'X', 'date' => today()->toDateString()])->assertStatus(422);
        $this->post(route('members.store'), $this->member(['uuid' => $foreignMember->uuid]))->assertStatus(422);

        $this->assertSame(0, Outing::query()->forAssociation($this->user->association_id)->count());
    }

    public function test_offline_replay_of_the_same_member_form_twice_creates_one_member(): void
    {
        $uuid = (string) Str::uuid();
        $operation = fn () => [
            'id' => (string) Str::uuid(), 'entity' => 'form', 'entity_uuid' => $uuid,
            'payload' => ['method' => 'POST', 'url' => '/membres', 'fields' => $this->member(['uuid' => $uuid]), 'label' => 'Nouveau membre'],
            'client_updated_at' => now()->subMinute()->toIso8601String(),
        ];

        $this->postJson(route('sync.store'), ['device_id' => 'phone', 'sent_at' => now()->toIso8601String(), 'operations' => [$operation(), $operation()]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied');

        $this->assertSame(1, Member::count());
    }
}
