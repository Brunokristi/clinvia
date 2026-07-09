<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchPublicSite;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicFooterBranchSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_settings_can_save_selected_insurance_companies(): void
    {
        ['branch' => $branch, 'admin' => $admin] = $this->createFixture();

        $this->actingAs($admin)->put(route('branches.settings.update', [
            'branch' => $branch->id,
        ]), [
            'contracted_insurance_companies' => ['vszp', 'dovera'],
            'show_other_branches_in_footer' => true,
        ])->assertSessionHasNoErrors();

        $branch->refresh();

        $this->assertSame(['vszp', 'dovera'], $branch->contracted_insurance_companies);
    }

    public function test_invalid_insurance_company_key_is_rejected(): void
    {
        ['branch' => $branch, 'admin' => $admin] = $this->createFixture();

        $this->actingAs($admin)->put(route('branches.settings.update', [
            'branch' => $branch->id,
        ]), [
            'contracted_insurance_companies' => ['custom'],
            'show_other_branches_in_footer' => false,
        ])->assertSessionHasErrors('contracted_insurance_companies.0');
    }

    public function test_empty_insurance_company_list_is_allowed(): void
    {
        ['branch' => $branch, 'admin' => $admin] = $this->createFixture();

        $this->actingAs($admin)->put(route('branches.settings.update', [
            'branch' => $branch->id,
        ]), [
            'contracted_insurance_companies' => [],
            'show_other_branches_in_footer' => false,
        ])->assertSessionHasNoErrors();

        $branch->refresh();

        $this->assertSame([], $branch->contracted_insurance_companies ?? []);
    }

    public function test_show_other_branches_in_footer_can_be_saved_as_true_and_false(): void
    {
        ['branch' => $branch, 'admin' => $admin] = $this->createFixture();

        $this->actingAs($admin)->put(route('branches.settings.update', [
            'branch' => $branch->id,
        ]), [
            'show_other_branches_in_footer' => true,
        ])->assertSessionHasNoErrors();

        $branch->refresh();
        $this->assertTrue($branch->show_other_branches_in_footer);

        $this->actingAs($admin)->put(route('branches.settings.update', [
            'branch' => $branch->id,
        ]), [
            'show_other_branches_in_footer' => false,
        ])->assertSessionHasNoErrors();

        $branch->refresh();
        $this->assertFalse($branch->show_other_branches_in_footer);
    }

    public function test_public_branch_payload_returns_resolved_insurance_company_objects(): void
    {
        ['branch' => $branch] = $this->createFixture();

        $branch->update([
            'contracted_insurance_companies' => ['vszp', 'dovera'],
        ]);

        $props = $this->publicPageProps(route('public.branch.home', [
            'branch' => $branch->slug,
        ]));

        $insurance = data_get($props, 'branch.contracted_insurance_companies');

        $this->assertSame([
            [
                'key' => 'vszp',
                'label' => 'VšZP',
                'full_name' => 'Všeobecná zdravotná poisťovňa',
            ],
            [
                'key' => 'dovera',
                'label' => 'Dôvera',
                'full_name' => 'Dôvera zdravotná poisťovňa',
            ],
        ], $insurance);
    }

    public function test_public_footer_data_returns_no_other_branches_when_setting_is_false(): void
    {
        ['company' => $company, 'branch' => $currentBranch] = $this->createFixture();

        $this->createBranch($company, 'other-visible', true, true);

        $currentBranch->update([
            'show_other_branches_in_footer' => false,
        ]);

        $props = $this->publicPageProps(route('public.branch.home', [
            'branch' => $currentBranch->slug,
        ]));

        $this->assertSame([], data_get($props, 'branch.other_company_branches'));
    }

    public function test_public_footer_data_returns_only_other_active_public_branches_when_enabled(): void
    {
        ['company' => $company, 'branch' => $currentBranch] = $this->createFixture();

        $visibleBranch = $this->createBranch($company, 'visible', true, true);
        $this->createBranch($company, 'inactive', false, true);
        $this->createBranch($company, 'private', true, false);

        $otherCompany = Company::query()->create([
            'legal_name' => 'Other Company',
            'slug' => 'other-company-' . Str::random(8),
            'is_active' => true,
        ]);
        $this->createBranch($otherCompany, 'foreign', true, true);

        $currentBranch->update([
            'show_other_branches_in_footer' => true,
        ]);

        $props = $this->publicPageProps(route('public.branch.home', [
            'branch' => $currentBranch->slug,
        ]));

        $otherBranches = data_get($props, 'branch.other_company_branches');

        $this->assertCount(1, $otherBranches);
        $this->assertSame($visibleBranch->id, $otherBranches[0]['id']);
        $this->assertSame($visibleBranch->name, $otherBranches[0]['name']);
    }

    public function test_current_branch_is_excluded_from_other_branches_payload(): void
    {
        ['company' => $company, 'branch' => $currentBranch] = $this->createFixture();

        $currentBranch->update([
            'show_other_branches_in_footer' => true,
        ]);

        $this->createBranch($company, 'second', true, true);

        $props = $this->publicPageProps(route('public.branch.home', [
            'branch' => $currentBranch->slug,
        ]));

        $ids = collect(data_get($props, 'branch.other_company_branches'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($currentBranch->id, $ids);
    }

    private function createFixture(): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Footer Company',
            'slug' => 'footer-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = $this->createBranch($company, 'main', true, true);

        $admin = User::query()->create([
            'first_name' => 'Footer',
            'last_name' => 'Admin',
            'email' => 'footer-admin-' . Str::random(8) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'admin');
    }

    private function createBranch(Company $company, string $slugSuffix, bool $isActive, bool $isPublicEnabled): Branch
    {
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Branch ' . $slugSuffix,
            'slug' => 'branch-' . $slugSuffix . '-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => $isActive,
            'booking_settings' => [
                'is_enabled' => true,
            ],
        ]);

        BranchPublicSite::query()->create([
            'branch_id' => $branch->id,
            'is_enabled' => $isPublicEnabled,
            'template' => 'default',
        ]);

        return $branch;
    }

    private function publicPageProps(string $url): array
    {
        $response = $this->get($url)->assertOk();
        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertMatchesRegularExpression('/data-page="([^"]+)"/', $content);

        preg_match('/data-page="([^"]+)"/', $content, $matches);
        $encodedPayload = $matches[1] ?? '';
        $decodedPayload = html_entity_decode($encodedPayload, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pagePayload = json_decode($decodedPayload, true);

        $this->assertIsArray($pagePayload);

        return $pagePayload['props'] ?? [];
    }
}
