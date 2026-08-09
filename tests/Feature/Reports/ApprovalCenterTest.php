<?php

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Enums\QuotationApprovalStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('lets admin and manager reach the approval center, but forbids other roles', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->get(route('approval-center.index'))->assertOk();
    $this->actingAs($this->manager)->get(route('approval-center.index'))->assertOk();
    $this->actingAs($sales)->get(route('approval-center.index'))->assertForbidden();
});

it('lists pending leave requests but not already-reviewed ones', function () {
    $someone = User::factory()->role(UserRole::Support)->create();
    LeaveRequest::factory()->create(['user_id' => $someone->id, 'status' => LeaveRequestStatus::Pending, 'type' => LeaveRequestType::FullDay]);
    LeaveRequest::factory()->create(['user_id' => $someone->id, 'status' => LeaveRequestStatus::Approved, 'type' => LeaveRequestType::FullDay]);

    $response = $this->actingAs($this->manager)->get(route('approval-center.index'));

    expect($response->viewData('leaveRequests'))->toHaveCount(1);
});

it('lists quotations pending approval but not approved or already-sent ones', function () {
    $customer = Customer::factory()->create();
    Quotation::factory()->create(['customer_id' => $customer->id, 'approval_status' => QuotationApprovalStatus::Pending]);
    Quotation::factory()->create(['customer_id' => $customer->id, 'approval_status' => QuotationApprovalStatus::Approved]);
    Quotation::factory()->status(QuotationStatus::Sent)->create(['customer_id' => $customer->id, 'approval_status' => QuotationApprovalStatus::Approved]);

    $response = $this->actingAs($this->manager)->get(route('approval-center.index'));

    expect($response->viewData('quotations'))->toHaveCount(1);
});

it('lists projects with an AI-drafted update still awaiting review', function () {
    $withDraft = Project::factory()->create();
    $withDraft->notes()->create(['body' => 'Draft update', 'ai_generated' => true, 'visible_to_client' => false]);

    $alreadyApproved = Project::factory()->create();
    $alreadyApproved->notes()->create(['body' => 'Sent update', 'ai_generated' => true, 'visible_to_client' => true]);

    $noDrafts = Project::factory()->create();

    $response = $this->actingAs($this->manager)->get(route('approval-center.index'));

    $projectIds = $response->viewData('projectsWithUpdates')->pluck('id');
    expect($projectIds)->toContain($withDraft->id)
        ->not->toContain($alreadyApproved->id)
        ->not->toContain($noDrafts->id);
});

it('sums pending leave requests, quotations, and project updates into one total', function () {
    $someone = User::factory()->role(UserRole::Support)->create();
    LeaveRequest::factory()->create(['user_id' => $someone->id, 'status' => LeaveRequestStatus::Pending, 'type' => LeaveRequestType::FullDay]);

    $customer = Customer::factory()->create();
    Quotation::factory()->create(['customer_id' => $customer->id, 'approval_status' => QuotationApprovalStatus::Pending]);

    $project = Project::factory()->create();
    $project->notes()->create(['body' => 'Draft', 'ai_generated' => true, 'visible_to_client' => false]);
    $project->notes()->create(['body' => 'Draft 2', 'ai_generated' => true, 'visible_to_client' => false]);

    $response = $this->actingAs($this->manager)->get(route('approval-center.index'));

    expect($response->viewData('totalCount'))->toBe(4);
});
