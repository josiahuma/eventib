<div class="mt-4 space-y-3" x-show="children > 0">
    <p class="form-label">How old are the children?</p>
    <p class="form-help">Choose each child’s age on the event date.</p>
    <template x-for="(age, index) in childAges" :key="index">
        <div><label class="form-label" :for="'child-age-' + index" x-text="'Child ' + (index + 1)"></label>
        <select class="form-select w-full" :id="'child-age-' + index" :name="'child_ages[' + index + ']'" x-model="childAges[index]" required>
            <option value="">Select age</option>
            @for($age = 0; $age <= 17; $age++)<option value="{{ $age }}">{{ $age === 0 ? 'Under 1 year' : $age . ($age === 1 ? ' year' : ' years') }}</option>@endfor
        </select></div>
    </template>
    @error('child_ages')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('child_ages.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
