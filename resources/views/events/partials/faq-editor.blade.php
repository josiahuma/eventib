<div class="form-card" x-data="{ items: @js(old('faqs', isset($event) ? ($event->faqs ?? []) : [])) }">
    <h3 class="form-section-title">Help guests plan ahead</h3>
    <p class="form-help mb-4">Add up to six FAQs about parking, accessibility, age restrictions, what to bring or refunds. State your own policies clearly.</p>
    <template x-for="(item, index) in items" :key="index">
        <div class="border rounded-xl p-4 mb-4">
            <label class="form-label" :for="'faq-q-' + index">Question</label>
            <input :id="'faq-q-' + index" :name="'faqs['+index+'][question]'" x-model="item.question" maxlength="150" class="form-input">
            <label class="form-label mt-3" :for="'faq-a-' + index">Answer</label>
            <textarea :id="'faq-a-' + index" :name="'faqs['+index+'][answer]'" x-model="item.answer" maxlength="1000" rows="3" class="form-input"></textarea>
            <button type="button" class="mt-3 text-sm underline" @click="items.splice(index,1)">Remove FAQ</button>
        </div>
    </template>
    <button type="button" class="form-secondary-btn" @click="items.push({question:'',answer:''})" :disabled="items.length >= 6">+ Add a question</button>
    @error('faqs')<p class="text-rose-700 text-sm mt-2">{{ $message }}</p>@enderror
</div>
