<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Jobs\SendPush;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'date_of_birth',
        'phone',
        'sex',
        'role',
        'enable_push_wash_appointment',
        'enable_push_renewal_subscription',
        'enable_push_special_promotions',
        'referral_id',
        'ref_code'
    ];

    public const ROLE_USER = 0; //Пользователь мойки
    public const ROLE_MANAGER = 1; //Менеджер мойки
    public const ROLE_ADMIN = 2; //Администратор системы

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'referral_id'
    ];

    protected $appends = [
        'referrals_count'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'points' => 'integer',
            'enable_push_special_promotions' => 'boolean',
            'enable_push_renewal_subscription' => 'boolean',
            'enable_push_wash_appointment' => 'boolean',
            'ban' => 'boolean',
            'date_of_birth' => 'date',
            'referrals_count' => 'integer',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function referral() : BelongsTo
    {
        return $this->belongsTo(User::class, 'referral_id');
    }

    public function referrals() : HasMany
    {
        return $this->hasMany(User::class, 'referral_id');
    }

    public function getReferralsCountAttribute() : int
    {
        return User::where('referral_id', $this->id)->count() ?? 0;
    }

    public function appointments() : HasMany
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function cars() : HasMany
    {
        return $this->hasMany(UserCar::class, 'user_id');
    }

    public function reviews() : HasMany
    {
        return $this->hasMany(Review::class, 'user_id', 'id');
    }

    public function packages() : HasMany
    {
        return $this->hasMany(UserPackage::class, 'user_id');
    }

    public function washing() : HasOne
    {
        return $this->hasOne(CarWash::class, 'manager_id');
    }

    public function transactions() : HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function getReferralCode()
    {
        return route('referral.link', $this->ref_code);
    }

    public function tickets() : BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'user_tickets');
    }

    public function getPurchasedTicketsAttribute()
    {
        $sub = DB::table('user_tickets')
            ->select('ticket_id', DB::raw('COUNT(*) as qty'))
            ->where('user_id', $this->id)
            ->groupBy('ticket_id');

        $tickets = Ticket::query()
            ->joinSub($sub, 'ut', function ($join) {
                $join->on('tickets.id', '=', 'ut.ticket_id');
            })
            ->select('tickets.*', 'ut.qty')
            ->paginate(10);

        return $tickets;
    }

    public function vouchers() : BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, 'user_vouchers')
            ->with('category')
            ->withPivot('code');
    }

    public function pushTokens() : HasMany
    {
        return $this->hasMany(UserPushToken::class, 'user_id');
    }

    public function setPhoneAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['phone'] = $value;

            return;
        }

        $deleted = is_string($value) && str_ends_with($value, '-del');
        $base = $deleted ? substr($value, 0, -4) : (string) $value;
        $normalized = \App\Support\Phone::normalize($base);
        $stored = $normalized ?? (\App\Support\Phone::digits($base) ?: $base);

        $this->attributes['phone'] = $deleted ? $stored.'-del' : $stored;
    }

    public function displayName(): string
    {
        $name = trim(implode(' ', array_filter([$this->surname, $this->name])));

        return $name !== '' ? $name : (string) $this->phone;
    }

    public function titleName(): string
    {
        $name = trim(implode(' ', array_filter(
            [$this->name, $this->surname],
            fn ($part) => trim((string) $part) !== ''
        )));

        if ($name !== '') {
            return $name;
        }

        return \App\Support\Phone::format($this->phone);
    }

    public function pickerLabel(): string
    {
        $name = trim(implode(' ', array_filter(
            [$this->name, $this->surname],
            fn ($part) => trim((string) $part) !== ''
        )));
        $contact = trim((string) $this->email);
        if ($contact === '') {
            $contact = \App\Support\Phone::format($this->phone);
        }
        if ($name === '') {
            return $contact !== '' ? $contact : '#'.$this->id;
        }
        if ($contact === '' || $contact === $name) {
            return $name;
        }

        return $name.' ('.$contact.')';
    }

    public function sendPush($title = [], $body = [], $url = 'https://app.geocar.ge/messages') : void
    {
        $pushTokens = $this->pushTokens()->first();
        if($pushTokens) {
            SendPush::dispatch($pushTokens->push_id, $title, $body, $url ? ['url' => $url] : null);
        }
    }

    public function verificationCodes() : HasMany
    {
        return $this->hasMany(VerificationCode::class, 'user_id');
    }
}
