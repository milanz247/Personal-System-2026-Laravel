<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { login } from '@/routes';
import { 
    User, 
    IdCard, 
    FileText, 
    Check, 
    ArrowLeft, 
    ArrowRight, 
    UploadCloud, 
    Trash2 
} from '@lucide/vue';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Fill in your details below to register',
    },
});

const currentStep = ref(1);

const form = useForm({
    // Step 1: Account
    email: '',
    password: '',
    password_confirmation: '',
    mobile_number: '',

    // Step 2: Personal
    full_name: '',
    date_of_birth: '',
    gender: '',
    ethnicity_or_religion: '',
    nic_number: '',
    driving_license_number: '',
    passport_number: '',

    // Step 3: Address & Docs
    current_address: '',
    permanent_address: '',
    birth_certificate: null as File | null,
    nic_front: null as File | null,
    nic_back: null as File | null,
});

const sameAddress = ref(false);

const handleSameAddressChange = (checked: boolean) => {
    sameAddress.value = checked;
    if (checked) {
        form.permanent_address = form.current_address;
    } else {
        form.permanent_address = '';
    }
};

// File refs for triggering inputs programmatically
const birthCertInput = ref<HTMLInputElement | null>(null);
const nicFrontInput = ref<HTMLInputElement | null>(null);
const nicBackInput = ref<HTMLInputElement | null>(null);

const triggerFileSelect = (field: 'birth_certificate' | 'nic_front' | 'nic_back') => {
    if (field === 'birth_certificate') birthCertInput.value?.click();
    if (field === 'nic_front') nicFrontInput.value?.click();
    if (field === 'nic_back') nicBackInput.value?.click();
};

const handleFileChange = (event: Event, field: 'birth_certificate' | 'nic_front' | 'nic_back') => {
    const files = (event.target as HTMLInputElement).files;
    if (files && files.length > 0) {
        form[field] = files[0];
    }
};

const handleFileDrop = (event: DragEvent, field: 'birth_certificate' | 'nic_front' | 'nic_back') => {
    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        form[field] = files[0];
    }
};

const removeFile = (field: 'birth_certificate' | 'nic_front' | 'nic_back') => {
    form[field] = null;
};

// Local step validation
const step1Errors = ref<Record<string, string>>({});
const step2Errors = ref<Record<string, string>>({});
const step3Errors = ref<Record<string, string>>({});

const validateStep1 = () => {
    step1Errors.value = {};
    if (!form.email) step1Errors.value.email = 'Email address is required.';
    else if (!/\S+@\S+\.\S+/.test(form.email)) step1Errors.value.email = 'Please enter a valid email.';
    
    if (!form.password) step1Errors.value.password = 'Password is required.';
    if (!form.password_confirmation) step1Errors.value.password_confirmation = 'Please confirm your password.';
    else if (form.password !== form.password_confirmation) step1Errors.value.password_confirmation = 'Passwords do not match.';
    
    if (!form.mobile_number) step1Errors.value.mobile_number = 'Mobile number is required.';
    
    return Object.keys(step1Errors.value).length === 0;
};

const validateStep2 = () => {
    step2Errors.value = {};
    if (!form.full_name) step2Errors.value.full_name = 'Full name is required.';
    if (!form.date_of_birth) step2Errors.value.date_of_birth = 'Date of birth is required.';
    if (!form.gender) step2Errors.value.gender = 'Gender is required.';
    if (!form.ethnicity_or_religion) step2Errors.value.ethnicity_or_religion = 'Ethnicity/Religion is required.';
    if (!form.nic_number) step2Errors.value.nic_number = 'NIC number is required.';
    
    return Object.keys(step2Errors.value).length === 0;
};

const validateStep3 = () => {
    step3Errors.value = {};
    if (!form.current_address) step3Errors.value.current_address = 'Current address is required.';
    if (!form.permanent_address) step3Errors.value.permanent_address = 'Permanent address is required.';
    if (!form.birth_certificate) step3Errors.value.birth_certificate = 'Birth certificate scan is required.';
    if (!form.nic_front) step3Errors.value.nic_front = 'NIC front scan is required.';
    if (!form.nic_back) step3Errors.value.nic_back = 'NIC back scan is required.';
    
    return Object.keys(step3Errors.value).length === 0;
};

const nextStep = () => {
    if (currentStep.value === 1 && validateStep1()) {
        currentStep.value = 2;
    } else if (currentStep.value === 2 && validateStep2()) {
        currentStep.value = 3;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value -= 1;
    }
};

const getStepForError = (errors: Record<string, string>): number => {
    const step1Keys = ['email', 'password', 'password_confirmation', 'mobile_number'];
    const step2Keys = ['full_name', 'date_of_birth', 'gender', 'ethnicity_or_religion', 'nic_number', 'driving_license_number', 'passport_number'];
    
    for (const key of Object.keys(errors)) {
        if (step1Keys.includes(key)) return 1;
        if (step2Keys.includes(key)) return 2;
    }
    return 3;
};

const submitForm = () => {
    if (!validateStep3()) return;

    form.post('/register', {
        onError: (errors) => {
            currentStep.value = getStepForError(errors);
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        }
    });
};
</script>

<template>
    <Head title="Register" />

    <div class="w-full">
        <!-- Progressive Stepper Tracker -->
        <div class="mb-8 flex items-center justify-between px-2">
            <div class="flex flex-1 items-center">
                <div 
                    :class="[
                        'flex size-8 items-center justify-center rounded-full text-xs font-semibold transition-all duration-300',
                        currentStep >= 1 ? 'bg-primary text-primary-foreground ring-4 ring-primary/20' : 'bg-muted text-muted-foreground'
                    ]"
                >
                    <Check v-if="currentStep > 1" class="size-4" />
                    <User v-else class="size-4" />
                </div>
                <div 
                    :class="[
                        'h-[2px] flex-1 mx-2 transition-all duration-500',
                        currentStep > 1 ? 'bg-primary' : 'bg-muted'
                    ]"
                ></div>
            </div>

            <div class="flex flex-1 items-center">
                <div 
                    :class="[
                        'flex size-8 items-center justify-center rounded-full text-xs font-semibold transition-all duration-300',
                        currentStep >= 2 ? 'bg-primary text-primary-foreground ring-4 ring-primary/20' : 'bg-muted text-muted-foreground'
                    ]"
                >
                    <Check v-if="currentStep > 2" class="size-4" />
                    <IdCard v-else class="size-4" />
                </div>
                <div 
                    :class="[
                        'h-[2px] flex-1 mx-2 transition-all duration-500',
                        currentStep > 2 ? 'bg-primary' : 'bg-muted'
                    ]"
                ></div>
            </div>

            <div 
                :class="[
                    'flex size-8 items-center justify-center rounded-full text-xs font-semibold transition-all duration-300',
                    currentStep === 3 ? 'bg-primary text-primary-foreground ring-4 ring-primary/20' : 'bg-muted text-muted-foreground'
                ]"
            >
                <FileText class="size-4" />
            </div>
        </div>

        <form @submit.prevent="submitForm" class="space-y-6">
            <!-- STEP 1: ACCOUNT DETAILS -->
            <div v-if="currentStep === 1" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        placeholder="email@example.com"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.email || step1Errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="mobile_number">Mobile Number</Label>
                    <Input
                        id="mobile_number"
                        type="text"
                        v-model="form.mobile_number"
                        required
                        placeholder="+1 (555) 019-2834"
                        autocomplete="tel"
                    />
                    <InputError :message="form.errors.mobile_number || step1Errors.mobile_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        required
                        placeholder="Create password"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password || step1Errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        required
                        placeholder="Confirm password"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation || step1Errors.password_confirmation" />
                </div>
            </div>

            <!-- STEP 2: IDENTITY & PERSONAL INFO -->
            <div v-if="currentStep === 2" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="full_name">Full Name</Label>
                    <Input
                        id="full_name"
                        type="text"
                        v-model="form.full_name"
                        required
                        placeholder="As shown in your NIC / Passport"
                    />
                    <InputError :message="form.errors.full_name || step2Errors.full_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="date_of_birth">Date of Birth</Label>
                    <Input
                        id="date_of_birth"
                        type="date"
                        v-model="form.date_of_birth"
                        required
                    />
                    <InputError :message="form.errors.date_of_birth || step2Errors.date_of_birth" />
                </div>

                <div class="grid gap-2">
                    <Label for="gender">Gender</Label>
                    <Select v-model="form.gender">
                        <SelectTrigger id="gender">
                            <SelectValue placeholder="Select gender" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="Male">Male</SelectItem>
                            <SelectItem value="Female">Female</SelectItem>
                            <SelectItem value="Other">Other</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.gender || step2Errors.gender" />
                </div>

                <div class="grid gap-2">
                    <Label for="ethnicity_or_religion">Ethnicity / Religion</Label>
                    <Input
                        id="ethnicity_or_religion"
                        type="text"
                        v-model="form.ethnicity_or_religion"
                        required
                        placeholder="e.g. Buddhist, Christian, Sinhalese"
                    />
                    <InputError :message="form.errors.ethnicity_or_religion || step2Errors.ethnicity_or_religion" />
                </div>

                <div class="grid gap-2">
                    <Label for="nic_number">NIC Number</Label>
                    <Input
                        id="nic_number"
                        type="text"
                        v-model="form.nic_number"
                        required
                        placeholder="National Identity Card number"
                    />
                    <InputError :message="form.errors.nic_number || step2Errors.nic_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="driving_license_number">Driving License Number (Optional)</Label>
                    <Input
                        id="driving_license_number"
                        type="text"
                        v-model="form.driving_license_number"
                        placeholder="Driving license number"
                    />
                    <InputError :message="form.errors.driving_license_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="passport_number">Passport Number (Optional)</Label>
                    <Input
                        id="passport_number"
                        type="text"
                        v-model="form.passport_number"
                        placeholder="Passport number"
                    />
                    <InputError :message="form.errors.passport_number" />
                </div>
            </div>

            <!-- STEP 3: ADDRESSES & DOCUMENTS -->
            <div v-if="currentStep === 3" class="space-y-6">
                <div class="grid gap-2">
                    <Label for="current_address">Current Address</Label>
                    <Input
                        id="current_address"
                        type="text"
                        v-model="form.current_address"
                        required
                        placeholder="Street address, City, ZIP"
                    />
                    <InputError :message="form.errors.current_address || step3Errors.current_address" />
                </div>

                <div class="flex items-center gap-2 py-1">
                    <input 
                        type="checkbox" 
                        id="same_address" 
                        :checked="sameAddress"
                        @change="handleSameAddressChange(($event.target as HTMLInputElement).checked)"
                        class="rounded border-input text-primary focus:ring-primary h-4 w-4"
                    />
                    <Label for="same_address" class="text-sm font-normal cursor-pointer select-none">
                        Permanent address is the same as current address
                    </Label>
                </div>

                <div class="grid gap-2">
                    <Label for="permanent_address">Permanent Address</Label>
                    <Input
                        id="permanent_address"
                        type="text"
                        v-model="form.permanent_address"
                        required
                        :disabled="sameAddress"
                        placeholder="As listed on your birth certificate / NIC"
                    />
                    <InputError :message="form.errors.permanent_address || step3Errors.permanent_address" />
                </div>

                <!-- Document Uploads -->
                <div class="space-y-4">
                    <!-- Birth Certificate -->
                    <div class="grid gap-2">
                        <Label>Birth Certificate (PDF or Image)</Label>
                        <div v-if="form.birth_certificate" class="flex items-center justify-between border rounded-lg p-3 bg-muted/30">
                            <span class="text-sm truncate max-w-[240px] font-medium">
                                {{ form.birth_certificate.name }}
                            </span>
                            <Button type="button" variant="ghost" size="icon" @click="removeFile('birth_certificate')" class="text-red-500 hover:text-red-700 hover:bg-red-50">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div 
                            v-else
                            class="border-2 border-dashed rounded-lg p-4 flex flex-col items-center justify-center gap-2 cursor-pointer hover:border-primary hover:bg-primary/5 transition duration-200"
                            @click="triggerFileSelect('birth_certificate')"
                            @dragover.prevent
                            @drop.prevent="handleFileDrop($event, 'birth_certificate')"
                        >
                            <UploadCloud class="size-8 text-muted-foreground" />
                            <span class="text-xs text-center font-medium text-muted-foreground">
                                Drag & drop or click to upload
                            </span>
                            <span class="text-[10px] text-muted-foreground">PDF, JPEG, PNG up to 10MB</span>
                        </div>
                        <input 
                            type="file" 
                            ref="birthCertInput" 
                            class="hidden" 
                            accept=".pdf,image/*" 
                            @change="handleFileChange($event, 'birth_certificate')" 
                        />
                        <InputError :message="form.errors.birth_certificate || step3Errors.birth_certificate" />
                    </div>

                    <!-- NIC Front -->
                    <div class="grid gap-2">
                        <Label>NIC Front Scan (PDF or Image)</Label>
                        <div v-if="form.nic_front" class="flex items-center justify-between border rounded-lg p-3 bg-muted/30">
                            <span class="text-sm truncate max-w-[240px] font-medium">
                                {{ form.nic_front.name }}
                            </span>
                            <Button type="button" variant="ghost" size="icon" @click="removeFile('nic_front')" class="text-red-500 hover:text-red-700 hover:bg-red-50">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div 
                            v-else
                            class="border-2 border-dashed rounded-lg p-4 flex flex-col items-center justify-center gap-2 cursor-pointer hover:border-primary hover:bg-primary/5 transition duration-200"
                            @click="triggerFileSelect('nic_front')"
                            @dragover.prevent
                            @drop.prevent="handleFileDrop($event, 'nic_front')"
                        >
                            <UploadCloud class="size-8 text-muted-foreground" />
                            <span class="text-xs text-center font-medium text-muted-foreground">
                                Drag & drop or click to upload
                            </span>
                            <span class="text-[10px] text-muted-foreground">PDF, JPEG, PNG up to 10MB</span>
                        </div>
                        <input 
                            type="file" 
                            ref="nicFrontInput" 
                            class="hidden" 
                            accept=".pdf,image/*" 
                            @change="handleFileChange($event, 'nic_front')" 
                        />
                        <InputError :message="form.errors.nic_front || step3Errors.nic_front" />
                    </div>

                    <!-- NIC Back -->
                    <div class="grid gap-2">
                        <Label>NIC Back Scan (PDF or Image)</Label>
                        <div v-if="form.nic_back" class="flex items-center justify-between border rounded-lg p-3 bg-muted/30">
                            <span class="text-sm truncate max-w-[240px] font-medium">
                                {{ form.nic_back.name }}
                            </span>
                            <Button type="button" variant="ghost" size="icon" @click="removeFile('nic_back')" class="text-red-500 hover:text-red-700 hover:bg-red-50">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div 
                            v-else
                            class="border-2 border-dashed rounded-lg p-4 flex flex-col items-center justify-center gap-2 cursor-pointer hover:border-primary hover:bg-primary/5 transition duration-200"
                            @click="triggerFileSelect('nic_back')"
                            @dragover.prevent
                            @drop.prevent="handleFileDrop($event, 'nic_back')"
                        >
                            <UploadCloud class="size-8 text-muted-foreground" />
                            <span class="text-xs text-center font-medium text-muted-foreground">
                                Drag & drop or click to upload
                            </span>
                            <span class="text-[10px] text-muted-foreground">PDF, JPEG, PNG up to 10MB</span>
                        </div>
                        <input 
                            type="file" 
                            ref="nicBackInput" 
                            class="hidden" 
                            accept=".pdf,image/*" 
                            @change="handleFileChange($event, 'nic_back')" 
                        />
                        <InputError :message="form.errors.nic_back || step3Errors.nic_back" />
                    </div>
                </div>
            </div>

            <!-- NAVIGATION BUTTONS -->
            <div class="flex items-center justify-between pt-4">
                <Button
                    v-if="currentStep > 1"
                    type="button"
                    variant="outline"
                    @click="prevStep"
                    :disabled="form.processing"
                >
                    <ArrowLeft class="size-4 mr-2" />
                    Back
                </Button>
                <div v-else></div>

                <Button
                    v-if="currentStep < 3"
                    type="button"
                    @click="nextStep"
                >
                    Next
                    <ArrowRight class="size-4 ml-2" />
                </Button>

                <Button
                    v-else
                    type="submit"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    Register
                </Button>
            </div>
        </form>

        <div class="text-center text-sm text-muted-foreground mt-6">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                >Log in</TextLink
            >
        </div>
    </div>
</template>
