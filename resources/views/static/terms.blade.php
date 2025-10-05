{{-- resources/views/terms.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
            Terms & Conditions
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 py-8 text-gray-700 space-y-6">
        <p><strong>Effective Date:</strong> October 5, 2025</p>
        <p><strong>Last Updated:</strong> October 5, 2025</p>

        <p>These Terms and Conditions (“Terms”) govern your use of <strong>Eventib</strong> (the “Platform”), including the <strong>Eventib Website</strong> and <strong>Eventib Scanner App</strong>. By using Eventib, you agree to these Terms.</p>

        <h3 class="text-xl font-semibold mt-6">1. Definitions</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Organizer:</strong> creates and manages events.</li>
            <li><strong>Attendee:</strong> registers for or purchases tickets.</li>
            <li><strong>Services:</strong> all tools for event creation, ticketing, and management.</li>
        </ul>

        <h3 class="text-xl font-semibold mt-6">2. Use of Service</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>Use Eventib only for lawful purposes.</li>
            <li>Provide accurate and up-to-date information.</li>
            <li>Do not misuse, resell, or reverse-engineer the Platform.</li>
        </ul>

        <h3 class="text-xl font-semibold mt-6">3. Organizer Responsibilities</h3>
        <p>Organizers are solely responsible for event accuracy, delivery, refunds, and compliance with local laws. Eventib acts only as a facilitator for event registration and payments.</p>

        <h3 class="text-xl font-semibold mt-6">4. Fees and Payments</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>Eventib charges transparent per-ticket or transaction fees.</li>
            <li>Payments are securely processed via Stripe or other gateways.</li>
            <li>Organizers are responsible for payout setup and taxes.</li>
        </ul>

        <h3 class="text-xl font-semibold mt-6">5. Refunds and Cancellations</h3>
        <p>Refunds are handled by the event organizer unless stated otherwise. Transaction fees may be non-refundable.</p>

        <h3 class="text-xl font-semibold mt-6">6. Intellectual Property</h3>
        <p>All content, branding, and source code are owned by <strong>Eventib Ltd.</strong> or its licensors. Users may not reproduce or distribute platform materials without permission.</p>

        <h3 class="text-xl font-semibold mt-6">7. Limitation of Liability</h3>
        <p>Eventib is provided “as is” without warranties. We are not liable for event cancellations, technical issues, or indirect damages. Liability is limited to the amount paid for the relevant service.</p>

        <h3 class="text-xl font-semibold mt-6">8. Account Termination</h3>
        <p>Eventib may suspend or terminate accounts that breach these Terms or engage in fraudulent activity.</p>

        <h3 class="text-xl font-semibold mt-6">9. Privacy and Data</h3>
        <p>Use of Eventib is subject to our <a href="{{ route('privacy') }}" class="text-orange-500 hover:underline">Privacy Policy</a>.</p>

        <h3 class="text-xl font-semibold mt-6">10. Modifications</h3>
        <p>We may update these Terms occasionally. Continued use after updates implies acceptance.</p>

        <h3 class="text-xl font-semibold mt-6">11. Governing Law</h3>
        <p>These Terms are governed by the laws of England and Wales. Disputes will be resolved under UK jurisdiction.</p>

        <h3 class="text-xl font-semibold mt-6">12. Contact</h3>
        <p class="mt-2">
            📧 <a href="mailto:support@eventib.com" class="text-orange-500 hover:underline">support@eventib.com</a><br>
            🌐 <a href="https://www.eventib.com" class="text-orange-500 hover:underline">www.eventib.com</a>
        </p>
    </div>
</x-app-layout>
