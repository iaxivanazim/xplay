<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\TableLedger;
use App\Models\User;
use App\Models\Role;

class TabOtpLifecycleTest extends TestCase
{
    protected function getAdmin(): User
    {
        $user = User::first();
        if ($user) {
            return $user;
        }

        $adminRole = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Administrator']
        );

        return User::firstOrCreate(
            ['username' => 'testadmin_otp'],
            [
                'card_id'  => 'CRD-OTP-01',
                'name'     => 'Test Admin OTP',
                'password' => bcrypt('password'),
                'role_id'  => $adminRole->id,
            ]
        );
    }

    protected function getOrCreateTab(): Tab
    {
        $tab = Tab::first();
        if (!$tab) {
            $tab = Tab::create([
                'tab_number'        => 'TAB-OTP-99',
                'table_name'        => 'VIP Player OTP Tab',
                'denomination'      => 1.00,
                'status'            => 1,
                'lock_status'       => 'unlocked',
                'connection_status' => 'online',
            ]);
        }

        TabSession::where('tab_id', $tab->id)
            ->where('status', 'active')
            ->update([
                'status'    => 'completed',
                'closed_at' => now(),
            ]);

        TabStatus::updateOrCreate(
            ['tab_id' => $tab->id],
            [
                'tab_number'      => $tab->tab_number,
                'current_balance' => 0.00,
                'current_otp'     => null,
                'status'          => 'idle',
                'lock_status'     => 'unlocked',
            ]
        );

        $tab->update(['lock_status' => 'unlocked']);

        return $tab;
    }

    public function test_cashin_opens_session_with_null_otp()
    {
        $admin = $this->getAdmin();
        $tab = $this->getOrCreateTab();

        $response = $this->actingAs($admin)->post("/dashboard/tab/{$tab->id}/cashin", [
            'amount'         => 100.00,
            'player_name'    => 'Player PIN Test',
        ]);

        $response->assertRedirect('/dashboard');

        // Status record should have balance, but current_otp must be NULL
        $status = TabStatus::where('tab_id', $tab->id)->first();
        $this->assertNotNull($status);
        $this->assertNull($status->current_otp, 'OTP column must remain null upon cashin');
        $this->assertEquals(100.00, (float)$status->current_balance);

        // Session record should also have otp = NULL
        $session = TabSession::where('tab_id', $tab->id)->where('status', 'active')->latest()->first();
        $this->assertNotNull($session);
        $this->assertNull($session->otp, 'tab_sessions.otp must be null until player creates it');

        // Heartbeat should signal requires_otp_prompt = true
        $hbResponse = $this->getJson("/api/v1/tabs/{$tab->id}/heartbeat");
        $hbResponse->assertStatus(200);
        $hbResponse->assertJson([
            'success'              => true,
            'otp_set'              => false,
            'requires_otp_prompt'  => true,
            'current_otp'          => null,
        ]);
    }

    public function test_first_lock_without_otp_returns_422_otp_required()
    {
        $tab = $this->getOrCreateTab();
        $status = TabStatus::where('tab_id', $tab->id)->first();
        $status->update([
            'current_balance' => 100.00,
            'current_otp'     => null,
            'lock_status'     => 'unlocked',
        ]);

        // Attempting to lock via API without providing player OTP
        $response = $this->postJson("/api/v1/tabs/{$tab->id}/lock", []);

        $response->assertStatus(422);
        $response->assertJson([
            'success'      => false,
            'code'         => 'OTP_REQUIRED',
            'requires_otp' => true,
        ]);

        // Verify tab was NOT locked
        $tab->refresh();
        $this->assertEquals('unlocked', $tab->lock_status);
    }

    public function test_first_lock_with_player_desired_otp_locks_tab_and_persists_otp()
    {
        $tab = $this->getOrCreateTab();
        $session = TabSession::create([
            'tab_id'          => $tab->id,
            'opened_by'       => $this->getAdmin()->id,
            'buyin_amount'    => 100.00,
            'opening_balance' => 100.00,
            'status'          => 'active',
            'opened_at'       => now(),
            'otp'             => null,
        ]);

        $status = TabStatus::where('tab_id', $tab->id)->first();
        $status->update([
            'current_balance' => 100.00,
            'current_otp'     => null,
            'lock_status'     => 'unlocked',
        ]);

        // Player submits their chosen PIN/OTP upon first lock
        $playerPin = '4321';
        $response = $this->postJson("/api/v1/tabs/{$tab->id}/lock", [
            'otp' => $playerPin,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'     => true,
            'lock_status' => 'locked',
            'otp_set'     => true,
            'current_otp' => $playerPin,
        ]);

        // Verify status and session both have this player-desired OTP
        $status->refresh();
        $this->assertEquals($playerPin, $status->current_otp);
        $this->assertEquals('locked', $status->lock_status);

        $session->refresh();
        $this->assertEquals($playerPin, $session->otp);

        // Heartbeat should now show otp_set = true and requires_otp_prompt = false
        $hbResponse = $this->getJson("/api/v1/tabs/{$tab->id}/heartbeat");
        $hbResponse->assertStatus(200);
        $hbResponse->assertJson([
            'success'             => true,
            'otp_set'             => true,
            'requires_otp_prompt' => false,
            'current_otp'         => $playerPin,
        ]);
    }

    public function test_unlocking_requires_valid_player_otp()
    {
        $tab = $this->getOrCreateTab();
        $playerPin = '9876';

        $tab->update(['lock_status' => 'locked']);
        $status = TabStatus::where('tab_id', $tab->id)->first();
        $status->update([
            'current_balance' => 100.00,
            'current_otp'     => $playerPin,
            'lock_status'     => 'locked',
        ]);

        // Attempt unlock with wrong OTP
        $wrongResponse = $this->postJson("/api/v1/tabs/{$tab->id}/unlock", [
            'otp' => '0000',
        ]);

        $wrongResponse->assertStatus(403);
        $wrongResponse->assertJson([
            'success' => false,
            'code'    => 'INVALID_OTP',
        ]);

        // Attempt unlock with correct player OTP
        $correctResponse = $this->postJson("/api/v1/tabs/{$tab->id}/unlock", [
            'otp' => $playerPin,
        ]);

        $correctResponse->assertStatus(200);
        $correctResponse->assertJson([
            'success'     => true,
            'lock_status' => 'unlocked',
        ]);

        $tab->refresh();
        $this->assertEquals('unlocked', $tab->lock_status);
    }

    public function test_subsequent_locks_do_not_require_re_prompt()
    {
        $tab = $this->getOrCreateTab();
        $playerPin = '9876';

        $tab->update(['lock_status' => 'unlocked']);
        $status = TabStatus::where('tab_id', $tab->id)->first();
        $status->update([
            'current_balance' => 100.00,
            'current_otp'     => $playerPin,
            'lock_status'     => 'unlocked',
        ]);

        // Player locks again - no otp needed in body because current_otp is already set
        $response = $this->postJson("/api/v1/tabs/{$tab->id}/lock", []);

        $response->assertStatus(200);
        $response->assertJson([
            'success'     => true,
            'lock_status' => 'locked',
            'current_otp' => $playerPin,
        ]);
    }

    public function test_dedicated_set_otp_endpoint()
    {
        $tab = $this->getOrCreateTab();
        $session = TabSession::create([
            'tab_id'          => $tab->id,
            'opened_by'       => $this->getAdmin()->id,
            'buyin_amount'    => 100.00,
            'opening_balance' => 100.00,
            'status'          => 'active',
            'opened_at'       => now(),
            'otp'             => null,
        ]);

        $response = $this->postJson("/api/v1/tabs/{$tab->id}/set-otp", [
            'otp' => '7788',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'     => true,
            'current_otp' => '7788',
        ]);

        $status = TabStatus::where('tab_id', $tab->id)->first();
        $this->assertEquals('7788', $status->current_otp);

        $session->refresh();
        $this->assertEquals('7788', $session->otp);
    }

    public function test_cashout_resets_otp_to_null()
    {
        $admin = $this->getAdmin();
        $tab = $this->getOrCreateTab();

        $status = TabStatus::where('tab_id', $tab->id)->first();
        $status->update([
            'current_balance' => 150.00,
            'current_otp'     => '5555',
            'status'          => 'active',
        ]);

        $response = $this->actingAs($admin)->post("/dashboard/tab/{$tab->id}/cashout");
        $response->assertRedirect('/dashboard');

        $status->refresh();
        $this->assertEquals(0.00, (float)$status->current_balance);
        $this->assertNull($status->current_otp, 'Cashout must reset OTP back to null');
    }
}
