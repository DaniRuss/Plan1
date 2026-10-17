@php
    $planItem = $plan->items->firstWhere('strategic_objective_id', $objective->id);
    $value = $planItem ? $planItem->target_value : '';

    // Check relevance
    $isOwner = $objective->department_id == $plan->department_id;
    $isPerformer = $objective->performers && in_array($plan->department_id, $objective->performers);
    $isRelevant = $isOwner || $isPerformer;

    $rowClass = $isRelevant ? 'table-primary' : ''; // Highlight relevant rows
    if (!$isRelevant && $objective->children->count() == 0)
        $rowClass = 'text-muted'; // Dim irrelevant leaf nodes
    $objectiveTreeIds = collect([$objective->id])
        ->merge($objective->children->pluck('id'))
        ->merge($objective->children->flatMap(fn ($child) => $child->children->pluck('id')));
    $hasPlanContent = $plan->items->contains(fn ($item) => $objectiveTreeIds->contains($item->strategic_objective_id));
@endphp

<tr class="{{ $rowClass }}">
    <td style="padding-left: {{ $padding }}px">
        <strong>{{ $objective->code }}</strong>
        @if($objective->children->count())
            <i class="fas fa-angle-right text-muted ml-1"></i>
        @endif
    </td>
    <td>
        {{ \Illuminate\Support\Str::limit($objective->description, 80) }}
        @if($plan->status === 'approved' && $hasPlanContent)
            <a href="{{ route('plans.objectives.download', [$plan, $objective]) }}" class="ml-2 text-danger" title="Download this objective from the approved plan"><i class="fas fa-file-pdf"></i></a>
        @endif
    </td>
    <td>{{ \App\Support\NumberFormatter::format($objective->weight) }}%</td>
    <td>{{ $objective->unit }}</td>
    <td style="vertical-align: middle;">
        @php
            $user = auth()->user();
            $objPerformers = $objective->performers ?? [];
            $itemPerformers = $planItem ? ($planItem->performers ?? []) : [];
            $itemExperts = $planItem ? ($planItem->experts ?? []) : [];
            $userDeptInPerformers = in_array($user->department_id, $objPerformers);
            $isChief = $user->isDepartmentChief() || $user->isDeptHead() || $user->isDepartmentDirectorate();
        @endphp

        @if($isRelevant && $isChief && $userDeptInPerformers && ($childDepartments->count() > 0 || $experts->count() > 0))
             <div class="p-1" style="max-height: 120px; overflow-y: auto; text-align: left; border: 1px dashed #ccc; border-radius: 4px;">
                <strong class="small text-muted d-block border-bottom mb-1">Dispatch to Units:</strong>
                <div class="custom-control custom-checkbox small mb-1">
                    <input type="checkbox" name="items[{{ $objective->id }}][performers][]" value="{{ $user->department_id }}" 
                        class="custom-control-input" id="perf_{{ $objective->id }}_{{ $user->department_id }}" 
                        {{ (empty($itemPerformers) || in_array($user->department_id, $itemPerformers)) ? 'checked' : '' }}
                        @cannot('edit_plan', $plan) disabled @endcannot>
                    <label class="custom-control-label font-weight-bold" for="perf_{{ $objective->id }}_{{ $user->department_id }}">Your Dept ({{ $user->department->name ?? 'N/A' }})</label>
                </div>
                
                @if($childDepartments->count() > 0)
                    @foreach($childDepartments as $child)
                        <div class="custom-control custom-checkbox small ml-2">
                            <input type="checkbox" name="items[{{ $objective->id }}][performers][]" value="{{ $child->id }}" 
                                class="custom-control-input" id="perf_{{ $objective->id }}_{{ $child->id }}"
                                {{ in_array($child->id, $itemPerformers) ? 'checked' : '' }}
                                @cannot('edit_plan', $plan) disabled @endcannot>
                            <label class="custom-control-label" for="perf_{{ $objective->id }}_{{ $child->id }}">{{ $child->name }}</label>
                        </div>
                    @endforeach
                @endif

                @if($experts->count() > 0)
                    <hr class="my-1">
                    <strong class="small text-muted">Experts:</strong>
                    @foreach($experts as $expert)
                        <div class="custom-control custom-checkbox small">
                            <input type="checkbox" name="items[{{ $objective->id }}][experts][]" value="{{ $expert->id }}" 
                                class="custom-control-input" id="exp_{{ $objective->id }}_{{ $expert->id }}"
                                {{ in_array($expert->id, $itemExperts) ? 'checked' : '' }}
                                @cannot('edit_plan', $plan) disabled @endcannot>
                            <label class="custom-control-label" for="exp_{{ $objective->id }}_{{ $expert->id }}">{{ $expert->name }}</label>
                        </div>
                    @endforeach
                @endif
             </div>
        @else
            @foreach($objPerformers as $perfId)
                @php $d = \App\Models\Department::find($perfId); @endphp
                <span class="badge badge-info mr-1" title="Assigned by Head Office">{{ $d->name ?? 'Unknown' }}</span>
                @if((int)$perfId === (int)$user->department_id)
                    <!-- Hidden input to preserve original performer if it is current user's dept -->
                    <input type="hidden" name="items[{{ $objective->id }}][performers][]" value="{{ $perfId }}">
                @endif
            @endforeach
            
            @if($itemExperts)
                @foreach($itemExperts as $expId)
                    @php $u = \App\Models\User::find($expId); @endphp
                    <span class="badge badge-secondary mr-1" title="Assigned Expert">{{ $u->name ?? 'Expert' }}</span>
                    <input type="hidden" name="items[{{ $objective->id }}][experts][]" value="{{ $expId }}">
                @endforeach
            @endif

            @if(empty($objPerformers) && empty($itemExperts))
                <span class="text-muted small">None</span>
            @endif
        @endif
    </td>
    <td>
        <input type="number" step="any" min="0" name="items[{{ $objective->id }}][annual_target]"
            class="plan-input text-right" data-original-number="{{ $planItem ? $planItem->annual_target : $objective->annual_target }}" value="{{ \App\Support\NumberFormatter::format($planItem ? $planItem->annual_target : $objective->annual_target,3,'.','') }}" 
            @cannot('edit_plan', $plan) disabled @endcannot>
    </td>
    @if($objective->children->count() == 0)
        <!-- Q1 -->
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][july]"      class="plan-input" data-original-number="{{ $planItem->july_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->july_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][august]"    class="plan-input" data-original-number="{{ $planItem->august_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->august_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][september]" class="plan-input" data-original-number="{{ $planItem->september_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->september_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value=''); autoFillQuarter(this, 'q1_{{ $objective->id }}')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][q1_target]" class="plan-input q-total" id="q1_{{ $objective->id }}" data-original-number="{{ $planItem->q1_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->q1_target ?? '',3,'.','') }}" readonly style="background:#e3f2fd;"></td>

        <!-- Q2 -->
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][october]"   class="plan-input" data-original-number="{{ $planItem->october_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->october_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][november]"  class="plan-input" data-original-number="{{ $planItem->november_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->november_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][december]"  class="plan-input" data-original-number="{{ $planItem->december_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->december_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value=''); autoFillQuarter(this, 'q2_{{ $objective->id }}')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][q2_target]" class="plan-input q-total" id="q2_{{ $objective->id }}" data-original-number="{{ $planItem->q2_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->q2_target ?? '',3,'.','') }}" readonly style="background:#e0f7fa;"></td>

        <!-- Q3 -->
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][january]"   class="plan-input" data-original-number="{{ $planItem->january_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->january_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][february]"  class="plan-input" data-original-number="{{ $planItem->february_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->february_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][march]"     class="plan-input" data-original-number="{{ $planItem->march_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->march_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value=''); autoFillQuarter(this, 'q3_{{ $objective->id }}')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][q3_target]" class="plan-input q-total" id="q3_{{ $objective->id }}" data-original-number="{{ $planItem->q3_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->q3_target ?? '',3,'.','') }}" readonly style="background:#fff8e1;"></td>

        <!-- Q4 -->
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][april]"     class="plan-input" data-original-number="{{ $planItem->april_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->april_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][may]"       class="plan-input" data-original-number="{{ $planItem->may_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->may_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value='')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][june]"      class="plan-input" data-original-number="{{ $planItem->june_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->june_target ?? '',3,'.','') }}" @cannot('edit_plan', $plan) disabled @endcannot oninput="validity.valid||(value=''); autoFillQuarter(this, 'q4_{{ $objective->id }}')"></td>
        <td><input type="number" step="any" min="0" name="items[{{ $objective->id }}][q4_target]" class="plan-input q-total" id="q4_{{ $objective->id }}" data-original-number="{{ $planItem->q4_target ?? '' }}" value="{{ \App\Support\NumberFormatter::format($planItem->q4_target ?? '',3,'.','') }}" readonly style="background:#fce4ec;"></td>
    @else
        <td colspan="16" class="text-right text-muted bg-light"><small>Sub-objectives contain the monthly target breakdown</small></td>
    @endif
</tr>

@if($objective->children)
    @foreach($objective->children as $child)
        @include('plans.partials.item_row', ['objective' => $child, 'padding' => $padding + 20])
    @endforeach
@endif
