use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Storage::fake('local');

    $response = $this->post(route('register.store'), [
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'mobile_number' => '0712345678',
        'full_name' => 'Test User',
        'date_of_birth' => '1998-05-15',
        'gender' => 'Male',
        'ethnicity_or_religion' => 'Buddhist',
        'nic_number' => '981234567V',
        'driving_license_number' => 'B1234567',
        'passport_number' => 'N1234567',
        'current_address' => '123 Main St, Colombo',
        'permanent_address' => '123 Main St, Colombo',
        'birth_certificate' => UploadedFile::fake()->create('birth.pdf', 500, 'application/pdf'),
        'nic_front' => UploadedFile::fake()->image('nic_front.jpg', 100, 100),
        'nic_back' => UploadedFile::fake()->image('nic_back.jpg', 100, 100),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'mobile_number' => '0712345678',
    ]);

    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'full_name' => 'Test User',
        'nic_number' => '981234567V',
    ]);
});