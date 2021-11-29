<?php

namespace App\Models\Auth;

use App\Models\Bundle;
use App\Models\Certificate;
use App\Models\ChapterStudent;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Traits\Uuid;
use App\Models\VideoProgress;
use Illuminate\Support\Collection;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Models\Auth\Traits\Scope\UserScope;
use App\Models\Auth\Traits\Method\UserMethod;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Auth\Traits\SendUserPasswordReset;
use App\Models\Auth\Traits\Attribute\UserAttribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Auth\Traits\Relationship\UserRelationship;
use App\Models\Earning;
use App\Models\TeacherProfile;
use App\Models\Withdraw;
use Gerardojbaez\Messenger\Contracts\MessageableInterface;
use Gerardojbaez\Messenger\Traits\Messageable;


/**
 * Class User.
 */
class User extends Authenticatable implements MessageableInterface
{
    use HasRoles,
        Notifiable,
        SendUserPasswordReset,
        SoftDeletes,
        UserAttribute,
        UserMethod,
        UserRelationship,
        UserScope,
        Uuid,
        Messageable;
      use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'dob',
        'phone',
        'gender',
        'address',
        'city',
        'pincode',
        'state',
        'country',
        'avatar_type',
        'avatar_location',
        'password',
        'password_changed_at',
        'active',
        'confirmation_code',
        'confirmed',
        'timezone',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @var array
     */
    protected $dates = ['last_login_at', 'deleted_at'];

    /**
     * The dynamic attributes from mutators that should be returned with the user object.
     * @var array
     */
    protected $appends = ['full_name','image'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'active' => 'boolean',
        'confirmed' => 'boolean',
    ];



    public function lessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_student');
    }

    public function chapters()
    {
        return $this->hasMany(ChapterStudent::class,'user_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_user');
    }

    public function bundles()
    {
        return $this->hasMany(Bundle::class);
    }


    public function invoices(){
        return $this->hasMany(Invoice::class);
    }


    public function getImageAttribute(){
        return $this->picture;
    }


    //Calc Watch Time
    public function getWatchTime(){
        $watch_time = VideoProgress::where('user_id','=',$this->id)->sum('progress');
        return $watch_time;

    }

    //Check Participation Percentage
    public function getParticipationPercentage(){
        $videos = Media::featured()->where('status','!=',0)->get();
        $count = $videos->count();
        $total_percentage = 0;
        if($count > 0) {
            foreach ($videos as $video) {
                $total_percentage = $total_percentage + $video->getProgressPercentage($this->id);
            }
            $percentage = $total_percentage /$count;
        }else{
            $percentage = 0;
        }
        return round($percentage,2);
    }

    //Get Certificates
    public function certificates(){
        return $this->hasMany(Certificate::class);
    }

    public function pendingOrders(){
        $orders = Order::where('status','=',0)
            ->where('user_id','=',$this->id)
            ->get();

        return $orders;
    }

    public function purchasedCourses(){
        $orders = Order::where('status','=',1)
            ->where('user_id','=',$this->id)
            ->pluck('id');
        $courses_id = OrderItem::whereIn('order_id',$orders)
            ->where('item_type','=',"App\Models\Course")
            ->pluck('item_id');
        $courses = Course::whereIn('id',$courses_id)
            ->get();
        return $courses;
    }

    public function purchasedBundles(){
        $orders = Order::where('status','=',1)
            ->where('user_id','=',$this->id)
            ->pluck('id');
        $bundles_id = OrderItem::whereIn('order_id',$orders)
            ->where('item_type','=',"App\Models\Bundle")
            ->pluck('item_id');
        $bundles = Bundle::whereIn('id',$bundles_id)
            ->get();

        return $bundles;
    }


    public function purchases(){
        $orders = Order::where('status','=',1)
            ->where('user_id','=',$this->id)
            ->pluck('id');
        $courses_id = OrderItem::whereIn('order_id',$orders)
            ->pluck('item_id');
        $purchases = Course::where('published','=',1)
            ->whereIn('id',$courses_id)
            ->get();
        return $purchases;
    }

    public function findForPassport($user)
    {
        $user = $this->where('email', $user)->first();
        if($user->hasRole('student')){
            return $user;
        }
    }

    /**
     * Get the teacher profile that owns the user.
     */
    public function teacherProfile(){
        return $this->hasOne(TeacherProfile::class);
    }

    /**
    * Get the earning owns the teacher.
    */
    public function earnings(){
        return $this->hasMany(Earning::class, 'user_id', 'id');
    }

    /**
    * Get the withdraw owns the teacher.
    */
    public function withdraws(){
        return $this->hasMany(Withdraw::class, 'user_id', 'id');
    }

}
