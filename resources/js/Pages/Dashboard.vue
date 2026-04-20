<script setup>
import { onMounted, ref } from 'vue';
import { loadStripe } from '@stripe/stripe-js';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stripeConnected: Boolean,
    payoutsEnabled: Boolean,
    // Assume you pass an array of books from your Controller
    books: Array
});

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_KEY);
let stripe = null;
let cardElement = null;

onMounted(async () => {
    stripe = await stripePromise;
    const elements = stripe.elements();
    cardElement = elements.create('card');
    cardElement.mount('#card-element');
});

const handleAction = async (endpoint) => {
    try {
        const { data } = await axios.post(endpoint);
        window.location.href = data.url;
    } catch (e) {
        alert("Action failed: " + (e.response?.data?.message || e.message));
    }
};

const sendTip = async (authorId) => {
    try {
        const stripeInstance = await stripePromise;
        const { data } = await axios.post(`/stripe/tip/${authorId}`);

        const { error, paymentIntent } = await stripeInstance.confirmCardPayment(data.clientSecret, {
            payment_method: { card: cardElement }
        });

        if (error) alert("Payment failed: " + error.message);
        else if (paymentIntent.status === 'succeeded') alert('Tip sent successfully!');
    } catch (e) {
        console.error(e);
        alert("Server error. Check console.");
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-2xl bg-white p-6 rounded shadow">

                <div class="mb-8 p-4 border rounded bg-gray-50">
                    <h3 class="font-bold mb-2">Your Account Status</h3>

                    <button v-if="!stripeConnected" @click="handleAction('/stripe/onboard')"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-full">
                        Connect Stripe
                    </button>

                    <button v-else-if="stripeConnected && !payoutsEnabled" @click="handleAction('/stripe/login')"
                        class="bg-yellow-500 text-white px-6 py-2 rounded-full">
                        Finish Stripe Setup
                    </button>

                    <p v-else class="text-green-600 font-semibold">✅ Your account is ready to receive tips!</p>
                </div>

                <div class="space-y-6">
                    <h2 class="text-xl font-bold">Featured Books</h2>

                    <div v-for="book in books" :key="book.id" class="flex justify-between items-center border-b pb-4">
                        <div>
                            <h3 class="font-semibold">{{ book.title }}</h3>
                            <p class="text-sm text-gray-600">by {{ book.author_name }}</p>
                        </div>

                        <button :disabled="!stripeConnected || !payoutsEnabled" @click="sendTip(book.author_id)"
                            class="bg-green-600 text-white px-4 py-1 rounded text-sm disabled:opacity-50">
                            Send $5 Tip
                        </button>
                    </div>

                    <div class="mt-8 border-t pt-4">
                        <p class="mb-2 font-medium">Secure Payment Details</p>
                        <div id="card-element" class="p-3 border rounded bg-gray-50"></div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>