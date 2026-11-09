<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EdrPlanDocument;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdrPlanDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_narration_can_be_uploaded_downloaded_and_replaced(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$expert] = $this->approvedPlan();
        $upload = fn ($name) => \Illuminate\Http\UploadedFile::fake()->create($name, 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'), [
            'year' => 2026, 'word_file' => $upload('Narration.docx'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $document = EdrPlanDocument::where('year', 2026)->firstOrFail();
        $oldPath = $document->word_file_path;
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($oldPath);
        $this->get(route('objectives.plan-narrative.attachment', ['year' => 2026]))
            ->assertOk()->assertDownload('Narration.docx');
        $this->put(route('objectives.plan-narrative.update'), ['year' => 2026, 'word_file' => $upload('Revised.docx')])
            ->assertSessionHasNoErrors()->assertRedirect();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($oldPath);
        $this->assertSame('Revised.docx', $document->fresh()->word_file_name);
        $this->put(route('objectives.plan-narrative.update'), [
            'year' => 2026, 'word_file' => \Illuminate\Http\UploadedFile::fake()->create('Bad.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('word_file');
        $this->actingAs(User::factory()->create())->get(route('objectives.plan-narrative.attachment', ['year' => 2026]))->assertForbidden();
    }

    public function test_plan_report_expert_can_prepare_one_narration_for_all_approved_plans(): void
    {
        [$expert, $plan] = $this->approvedPlan();
        $this->actingAs($expert)->get(route('objectives.plan-narrative.edit', ['year' => 2026]))
            ->assertOk()->assertSee('One narration covers all approved departmental plans');
        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'), [
            'year' => 2026,
            'introduction' => 'Official introduction', 'past_performance' => 'Past performance',
            'strategic_issues' => 'Strategic issues', 'implementation_strategies' => 'Implementation strategy',
            'monitoring_evaluation' => 'Monitoring approach', 'challenges_solutions' => 'Challenges and solutions',
        ])->assertRedirect(route('objectives.plan-narrative.edit', ['year' => 2026]));
        $this->assertDatabaseHas('edr_plan_documents', ['year' => 2026, 'introduction' => 'Official introduction', 'prepared_by' => $expert->id]);
    }

    public function test_approved_plan_and_single_objective_are_downloadable(): void
    {
        [$expert, $plan, $objective] = $this->approvedPlan();
        EdrPlanDocument::create(['year' => 2026, 'introduction' => 'Introduction', 'prepared_by' => $expert->id]);
        $this->actingAs($expert)->get(route('plans.download', $plan))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($expert)->get(route('plans.objectives.download', [$plan, $objective]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($expert)->get(route('objectives.download-approved'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $plan->load(['department','items.strategicObjective']);
        $this->view('edr_plans.organization_approved_plans_pdf', [
            'plans'=>collect([$plan]), 'narrative'=>null, 'year'=>2026,
            'departmentNames'=>Department::pluck('name','id'),
        ])->assertSee('APPROVED ANNUAL PLAN (2026)')
          ->assertSee('Approved railway objective')
          ->assertDontSee('1. Introduction');
    }

    public function test_section_four_is_generated_from_approved_objectives_and_activities(): void
    {
        [$expert,$plan,$objective]=$this->approvedPlan();
        $plan->load(['department','items.strategicObjective']);
        $departmentNames=Department::pluck('name','id');

        $this->view('edr_plans.organization_plan_pdf',[
            'plans'=>collect([$plan]),'narrative'=>null,'year'=>2026,'departmentNames'=>$departmentNames,
        ])->assertSee('4. Objectives and Detailed Activities')->assertSee('Approved railway objective')->assertSee('Plan and Report');
    }

    public function test_preview_action_saves_current_narration_before_downloading(): void
    {
        [$expert] = $this->approvedPlan();

        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'), [
            'year' => 2026,
            'introduction' => 'Text entered immediately before preview',
            'past_performance' => 'Saved past performance',
            'strategic_issues' => 'Saved strategic issues',
            'implementation_strategies' => 'Saved implementation',
            'monitoring_evaluation' => 'Saved monitoring',
            'challenges_solutions' => 'Saved challenges',
            'workflow_action' => 'save_and_preview',
        ])->assertRedirect(route('objectives.download-approved', ['year' => 2026, 'preview' => 1]));

        $this->assertDatabaseHas('edr_plan_documents', [
            'year' => 2026,
            'introduction' => 'Text entered immediately before preview',
        ]);

        $this->actingAs($expert)->get(route('objectives.download-approved', ['year'=>2026,'preview'=>1]))
            ->assertOk()
            ->assertHeader('content-type','application/pdf')
            ->assertHeader('content-disposition','inline; filename=Organization_EDR_Plan_2026_Narration_Preview.pdf');

        $plan = Plan::with(['department','items.strategicObjective'])->where('year', 2026)->firstOrFail();
        $this->view('edr_plans.organization_plan_pdf', [
            'plans'=>collect([$plan]), 'narrative'=>EdrPlanDocument::where('year',2026)->first(),
            'year'=>2026, 'departmentNames'=>Department::pluck('name','id'),
        ])->assertSee('Approved Action Plan Schedule (Plan Only)')
          ->assertSee('Annual Plan')
          ->assertDontSee('<th>Actual</th>', false);
    }

    public function test_approved_exports_use_system_logo_name_and_document_brand_color(): void
    {
        [$expert,$plan]=$this->approvedPlan();
        \App\Models\Setting::set('site_name','Railway Planning System','branding');
        \App\Models\Setting::set('site_abbv','RPS','branding');
        \App\Models\Setting::set('document_brand_color','#277967','branding');
        $plan->load(['department','items.strategicObjective']);
        $data=['plans'=>collect([$plan]),'narrative'=>null,'year'=>2026,'departmentNames'=>Department::pluck('name','id')];
        foreach (['edr_plans.organization_approved_plans_pdf','edr_plans.organization_plan_pdf'] as $view) {
            $this->view($view,$data)->assertSee('Railway Planning System')->assertSee('data:image/png;base64,',false)->assertSee('#277967',false);
        }
        $response=app(\App\Services\OrganizationPlanWordExporter::class)->download($data['plans'],null,$data['departmentNames'],2026);
        $path=$response->getFile()->getPathname();
        try {
            $zip=new \ZipArchive();
            $this->assertTrue($zip->open($path));
            $header=$zip->getFromName('word/header1.xml');
            $this->assertStringContainsString('Railway Planning System',$header);
            $this->assertStringContainsString('277967',$header);
            $this->assertStringContainsString('drawing',$header);
            $zip->close();
        } finally { unlink($path); }
        // A real PDF artifact for reviewing the template, not just its HTML.
        $directory=base_path('tmp/pdfs');
        if (!is_dir($directory)) mkdir($directory,0777,true);
        $previewPlan=clone $plan;
        $previewPlan->setRelation('items',collect(range(1,80))->map(function ($index) use ($plan) {
            $item=clone $plan->items->first();
            $objective=clone $item->strategicObjective;
            $objective->id=100000+$index;
            $item->strategic_objective_id=$objective->id;
            $objective->code='1.'.$index;
            $objective->description='Approved railway activity '.$index;
            if ($index===1) $objective->performers=[910001,910002];
            $item->setRelation('strategicObjective',$objective);
            return $item;
        }));
        $data['plans']=collect([$previewPlan]);
        $data['departmentNames']=$data['departmentNames']->put(910001,'Commercial')->put(910002,'Passenger');
        file_put_contents($directory.'/approved-plan-branding.html',view('edr_plans.organization_approved_plans_pdf',$data)->render());
        file_put_contents($directory.'/approved-plan-branding.pdf',\Barryvdh\DomPDF\Facade\Pdf::loadView('edr_plans.organization_approved_plans_pdf',$data)->setPaper('a4','landscape')->output());
    }

    public function test_plan_export_interleaves_quarters_and_lists_lead_with_all_contributors(): void
    {
        [$expert,$plan,$objective]=$this->approvedPlan();
        $commercial=Department::create(['name'=>'Commercial','code'=>'COM','is_active'=>true]);
        $passenger=Department::create(['name'=>'Passenger','code'=>'PSG','is_active'=>true]);
        $objective->update(['performers'=>[$commercial->id,$passenger->id]]);
        $plan->load(['department','items.strategicObjective']);
        $data=['plans'=>collect([$plan]),'narrative'=>null,'year'=>2026,'departmentNames'=>Department::pluck('name','id')];
        $this->view('edr_plans.objectives_pdf',['objectives'=>collect([$objective]),'title'=>'Approved objective','departmentNames'=>$data['departmentNames']])->assertSee('Commercial')->assertSee('Passenger');
        $html=view('edr_plans.organization_approved_plans_pdf',$data)->render();
        $this->assertStringNotContainsString('<th>Department</th>',$html);
        foreach (['Plan and Report (Lead)','Commercial','Passenger'] as $name) $this->assertStringContainsString($name,$html);
        preg_match_all('/<th[^>]*>(.*?)<\/th>/s',$html,$matches);
        $headers=array_map('strip_tags',$matches[1]);
        foreach (['Sep Plan'=>'Q1 Plan','Dec Plan'=>'Q2 Plan','Mar Plan'=>'Q3 Plan','Jun Plan'=>'Q4 Plan'] as $month=>$quarter) {
            $this->assertSame($quarter,$headers[array_search($month,$headers,true)+1]);
        }
        $response=app(\App\Services\OrganizationPlanWordExporter::class)->download($data['plans'],null,$data['departmentNames'],2026);
        $path=$response->getFile()->getPathname();
        try {
            $zip=new \ZipArchive(); $zip->open($path);
            $xml=$zip->getFromName('word/document.xml'); $zip->close();
            foreach (['Plan and Report (Lead)','Commercial','Passenger'] as $name) $this->assertStringContainsString($name,$xml);
            $doc=new \DOMDocument(); $doc->loadXML($xml);
            $xp=new \DOMXPath($doc); $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $found=false;
            foreach ($xp->query('//w:tbl/w:tr[1]') as $row) {
                $labels=[];
                foreach ($xp->query('./w:tc',$row) as $cell) $labels[]=trim($cell->textContent);
                if (!in_array('Jul',$labels,true)) continue;
                $found=true;
                foreach (['Sep'=>'Q1','Dec'=>'Q2','Mar'=>'Q3','Jun'=>'Q4'] as $month=>$quarter) $this->assertSame($quarter,$labels[array_search($month,$labels,true)+1]);
            }
            $this->assertTrue($found);
        } finally { unlink($path); }
    }

    public function test_download_uses_clean_report_heading_without_generated_plan_titles(): void
    {
        [$expert, $plan] = $this->approvedPlan();
        $plan->update(['title' => 'Approved Objectives 2026 - Plan and Report']);
        $html = view('edr_plans.organization_approved_plans_pdf', [
            'plans' => collect([$plan->load('department', 'items.strategicObjective')]),
            'year' => 2026, 'departmentNames' => Department::pluck('name', 'id'),
        ])->render();
        $this->assertStringContainsString('APPROVED ANNUAL PLAN (2026)', $html);
        $this->assertStringNotContainsString($plan->title, $html);
        $this->assertStringNotContainsString('colspan="23" class="dept"', $html);
        $this->assertStringNotContainsString('It contains no narration', $html);
    }

    public function test_combined_schedule_has_unique_objectives_in_natural_code_order(): void
    {
        [$expert, $plan, $objective] = $this->approvedPlan();
        $other = Department::create(['name'=>'Commercial', 'code'=>'COM', 'reporting_type'=>'monthly', 'is_active'=>true]);
        $second = Plan::create(['title'=>'Other approved plan','type'=>'annual','year'=>2026,'department_id'=>$other->id,'status'=>'approved','created_by'=>$expert->id]);
        PlanItem::create(['plan_id'=>$second->id,'strategic_objective_id'=>$objective->id,'annual_target'=>99,'performers'=>[$other->id]]);
        foreach (['1.10','1.2'] as $code) {
            $child = StrategicObjective::create(['code'=>$code,'description'=>'Activity '.$code,'status'=>'approved','department_id'=>$other->id,'weight'=>1]);
            PlanItem::create(['plan_id'=>$second->id,'strategic_objective_id'=>$child->id,'annual_target'=>1]);
        }
        $plans = Plan::with('department','items.strategicObjective')->whereIn('id',[$second->id,$plan->id])->get();
        $html = view('edr_plans.partials.schedule',['schedulePlans'=>$plans,'departmentNames'=>Department::pluck('name','id')])->render();
        $this->assertSame(1, substr_count($html, 'Approved railway objective'));
        $this->assertStringNotContainsString('class="dept"', $html);
        $this->assertLessThan(strpos($html, 'Activity 1.10'), strpos($html, 'Activity 1.2'));
        $this->assertStringContainsString('Commercial', $html);
        $rows = app(\App\Services\ApprovedPlanExport::class)->items($plans);
        $this->assertEquals(12, $rows->first()->annual_target);
    }

    private function approvedPlan(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Plan and Report', 'code' => 'PRD', 'reporting_type' => 'monthly', 'is_active' => true]);
        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'plan_report_department_expert')->value('id')]);
        $objective = StrategicObjective::create(['code' => '1', 'description' => 'Approved railway objective', 'weight' => 10, 'unit' => 'Number', 'department_id' => $department->id, 'lead_department_id' => $department->id, 'performers' => [$department->id], 'annual_target' => 12, 'status' => 'approved', 'is_active' => true]);
        $plan = Plan::create(['title' => 'Annual EDR Plan', 'type' => 'annual', 'year' => 2026, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $expert->id]);
        PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'annual_target' => 12, 'july_target' => 1, 'weight' => 10, 'unit' => 'number', 'performers' => [$department->id]]);
        return [$expert, $plan, $objective];
    }
}
