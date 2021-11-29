<?php

namespace App\Http\Controllers\v1;

use App\Helpers\General\EarningHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FileUploadTrait;
use App\Http\Requests\Frontend\User\UpdatePasswordRequest;
use App\Http\Requests\Frontend\User\UpdateProfileRequest;
use App\Mail\Frontend\Contact\SendContact;
use App\Mail\OfflineOrderMail;
use App\Models\Auth\Traits\SendUserPasswordReset;
use App\Models\Auth\User;
use App\Models\Blog;
use App\Models\BlogComment;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Config;
use App\Models\Contact;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Faq;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Question;
use App\Models\QuestionsOption;
use App\Models\Reason;
use App\Models\Review;
use App\Models\Sponsor;
use App\Models\System\Session;
use App\Models\Tag;
use App\Models\Tax;
use App\Models\Test;
use App\Models\Testimonial;
use App\Models\TestsResult;
use App\Models\TestsResultsAnswer;
use App\Models\VideoProgress;
use App\Repositories\Frontend\Auth\UserRepository;
use Arcanedev\NoCaptcha\Rules\CaptchaRule;
use Carbon\Carbon;
use DevDojo\Chatter\Events\ChatterAfterNewResponse;
use DevDojo\Chatter\Events\ChatterBeforeNewDiscussion;
use DevDojo\Chatter\Events\ChatterBeforeNewResponse;
use DevDojo\Chatter\Mail\ChatterDiscussionUpdated;
use Harimayco\Menu\Models\MenuItems;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Cart;
use DevDojo\Chatter\Events\ChatterAfterNewDiscussion;
use Event;
use DevDojo\Chatter\Models\Models;
use DevDojo\Chatter\Helpers\ChatterHelper as Helper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Purifier;
use Messenger;
use Newsletter;


class ApiController extends Controller
{
    use FileUploadTrait;
    use SendsPasswordResetEmails;


    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }


    public function __invoke(Request $request)
    {
        $this->validateEmail($request);
        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink(
            $request->only('email')
        );
        return $response == Password::RESET_LINK_SENT
            ? response()->json(['status' => 'success', 'message' => 'Reset link sent to your email.'], 201)
            : response()->json(['status' => 'failure', 'message' => 'Unable to send reset link. No Email found.'], 401);
    }


    /**
     * Get the Signup Form
     *
     * @return [json] config object
     */
    public function signupForm()
    {
        $fields = [];
        if (config('registration_fields') != NULL) {
            $fields = json_decode(config('registration_fields'), true);
        }
//        if (config('access.captcha.registration') > 0) {
//            $fields[] = ['name' => 'g-recaptcha-response', 'type' => 'captcha'];
//        }
        return response()->json(['status' => 'success', 'fields' => $fields]);
    }

    public function signup(Request $request)
    {
        $validation = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
//            'g-recaptcha-response' => (config('access.captcha.registration') ? ['required', new CaptchaRule()] : ''),
        ], [
//            'g-recaptcha-response.required' => __('validation.attributes.frontend.captcha'),
        ]);

        if (!$validation) {
            return response()->json(['errors' => $validation->errors()]);
        }
        $user = new User([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        $user->dob = isset($request->dob) ? $request->dob : NULL;
        $user->phone = isset($request->phone) ? $request->phone : NULL;
        $user->gender = isset($request->gender) ? $request->gender : NULL;
        $user->address = isset($request->address) ? $request->address : NULL;
        $user->city = isset($request->city) ? $request->city : NULL;
        $user->pincode = isset($request->pincode) ? $request->pincode : NULL;
        $user->state = isset($request->state) ? $request->state : NULL;
        $user->country = isset($request->country) ? $request->country : NULL;
        $user->save();

        $userForRole = User::find($user->id);
        $userForRole->confirmed = 1;
        $userForRole->save();
        $userForRole->assignRole('student');
        $user->save();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully created user!'
        ], 201);
    }

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);
        $credentials = request(['email', 'password']);
        if (!Auth::attempt($credentials))
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        $user = $request->user();
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()
        ]);
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        auth()->user()->token()->revoke();
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Get the App Config
     *
     * @return [json] config object
     */
    public function getConfig(Request $request)
    {
        $data = ['font_color', 'contact_data', 'counter', 'total_students', 'total_courses', 'total_teachers', 'logo_b_image', 'logo_w_image', 'logo_white_image', 'contact_data', 'footer_data', 'app.locale', 'app.display_type', 'app.currency', 'app.name', 'app.url', 'access.captcha.registration', 'paypal.active', 'payment_offline_active'];
        $json_arr = [];
        $config = Config::whereIn('key', $data)->select('key', 'value')->get();
        foreach ($config as $data) {
            if ((array_first(explode('_', $data->key)) == 'logo') || (array_first(explode('_', $data->key)) == 'favicon')) {
                $data->value = asset('storage/logos/' . $data->value);
            }
            $json_arr[$data->key] = (is_null(json_decode($data->value, true))) ? $data->value : json_decode($data->value, true);
        }
        return response()->json(['status' => 'success', 'data' => $json_arr]);
    }


    /**
     * Get  courses
     *
     * @return [json] course object
     */
    public function getCourses(Request $request)
    {
        $types = ['popular', 'trending', 'featured'];
        $type = ($request->type) ? $request->type : null;
        if ($type != null) {
            if (in_array($type, $types)) {
                $courses = Course::where('published', '=', 1)
                    ->where($type, '=', 1)
                    ->paginate(10);
            } else {
                return response()->json(['status' => 'failure', 'message' => 'Invalid Request']);
            }
        } else {
            $courses = Course::where('published', '=', 1)
                ->paginate(10);
        }

        return response()->json(['status' => 'success', 'type' => $type, 'result' => $courses]);

    }

    /**
     * Search Basic
     *
     * @return [json] Course / Bundle / Blog object
     */
    public function search(Request $request)
    {
        $result = NULL;
        if ($request->type) {

            if ($request->type == 1) {
                $result = Course::where('title', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('description', 'LIKE', '%' . $request->q . '%')
                    ->where('published', '=', 1)
                    ->with('teachers')
                    ->paginate(10);

            } elseif ($request->type == 2) {
                $result = Bundle::where('title', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('description', 'LIKE', '%' . $request->q . '%')
                    ->where('published', '=', 1)
                    ->with('user')
                    ->paginate(10);

            } elseif ($request->type == 3) {
                $result = Blog::where('title', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('content', 'LIKE', '%' . $request->q . '%')
                    ->with('author')
                    ->paginate(10);
            }

        }
        $type = $request->type;
        $q = $request->q;
        return response()->json(['status' => 'success', 'q' => $q, 'type' => $type, 'result' => $result]);

    }

    /**
     * Latest News / Blog
     *
     * @return [json] Blog object
     */
    public function getLatestNews(Request $request)
    {
        $blog = Blog::orderBy('created_at', 'desc')
            ->select('id', 'category_id', 'user_id', 'title', 'slug', 'content', 'image')
            ->paginate(10);
        return response()->json(['status' => 'success', 'result' => $blog]);
    }


    /**
     * Get Latest Testimonials
     *
     * @return [json] Testimonial object
     */
    public function getTestimonials(Request $request)
    {
        $testimonials = Testimonial::where('status', '=', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json(['status' => 'success', 'result' => $testimonials]);
    }

    /**
     * Get Teachers
     *
     * @return [json] Teacher object
     */
    public function getTeachers(Request $request)
    {
        $teachers = User::role('teacher')->with('teacherProfile')->paginate(10);
        if ($teachers == null) {
            return response()->json(['status' => 'failure', 'result' => null]);
        }
        return response()->json(['status' => 'success', 'result' => $teachers]);
    }

    /**
     * Get Single Teacher
     *
     * @return [json] Teacher object
     */
    public function getSingleTeacher(Request $request)
    {
        $teacher = User::role('teacher')->find($request->teacher_id);
        if ($teacher == null) {
            return response()->json(['status' => 'failure', 'result' => null]);
        }
        $courses = $teacher->courses->take(5);
        $bundles = $teacher->bundles->take(5);
        $profile = $teacher->teacherProfile->first();
        return response()->json(['status' => 'success', 'result' => ['teacher' => $teacher, 'courses' => $courses, 'bundles' => $bundles, 'profile' => $profile]]);
    }

    public function getSingleUser(Request $request)
    {
        $user = User::find($request->user_id);
        if ($user == null) {
            return response()->json(['status' => 'failure', 'result' => null]);
        }
        return response()->json(['status' => 'success', 'result' => ['user' => $user]]);
    }

    /**
     * Get Teacher Courses
     *
     * @return [json] Teacher Courses object
     */
    public function getTeacherCourses(Request $request)
    {
        $teacher = User::role('teacher')->find($request->teacher_id);
        if ($teacher == null) {
            return response()->json(['status' => 'failure', 'result' => null]);
        }
        $courses = $teacher->courses()->paginate(10);
        return response()->json(['status' => 'success', 'result' => ['teacher' => $teacher, 'courses' => $courses]]);
    }

    /**
     * Get Teacher Bundles
     *
     * @return [json] Teacher Bundles object
     */
    public function getTeacherBundles(Request $request)
    {
        $teacher = User::role('teacher')->find($request->teacher_id);
        if ($teacher == null) {
            return response()->json(['status' => 'failure', 'result' => null]);
        }
        $bundles = $teacher->bundles()->paginate(10);
        return response()->json(['status' => 'success', 'result' => ['teacher' => $teacher, 'bundles' => $bundles]]);
    }

    /**
     * Get FAQs
     *
     * @return [json] FAQs object
     */
    public function getFaqs()
    {

        $faqs = Faq::whereHas('category')
            ->where('status', '=', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json(['status' => 'success', 'result' => $faqs]);
    }

    /**
     * Get WHy Us Reasons
     *
     * @return [json] Reason object
     */
    public function getWhyUs()
    {
        $reasons = Reason::where('status', '=', 1)->paginate(10);
        return response()->json(['status' => 'success', 'result' => $reasons]);

    }

    /**
     * Get Sponsors
     *
     * @return [json] Sponsors object
     */
    public function getSponsors()
    {
        $sponsors = Sponsor::where('status', '=', 1)->paginate(10);
        return response()->json(['status' => 'success', 'result' => $sponsors]);
    }

    /**
     * Save Contact Us Request
     *
     * @return [json] Success feedback
     */
    public function saveContactUs(Request $request)
    {
        $validation = $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
            'message' => 'required'
        ]);
        if (!$validation) {
            return response()->json(['status' => 'failure', 'errors' => $validation->errors()]);
        }

        $contact = new Contact();
        $contact->name = $request->name;
        $contact->number = $request->number;
        $contact->email = $request->email;
        $contact->message = $request->message;
        $contact->save();

        Mail::send(new SendContact($request));
        return response()->json(['status' => 'success']);


    }

    /**
     * Get Single Course
     *
     * @return [json] Success feedback
     */
    public function getSingleCourse(Request $request)
    {
        $continue_course = NULL;
        $course_timeline = NULL;
        $course = Course::withoutGlobalScope('filter')->with('teachers', 'category')->where('id', '=', $request->course_id)->with('publishedLessons')->first();
        if ($course == null) {
            return response()->json(['status' => 'failure', 'result' => NULL]);
        }

        $purchased_course = \Auth::check() && $course->students()->where('user_id', \Auth::id())->count() > 0;
        $course_rating = 0;
        $total_ratings = 0;
        $completed_lessons = NULL;
        $is_reviewed = false;
        if (auth()->check() && $course->reviews()->where('user_id', '=', auth()->user()->id)->first()) {
            $is_reviewed = true;
        }
        if ($course->reviews->count() > 0) {
            $course_rating = $course->reviews->avg('rating');
            $total_ratings = $course->reviews()->where('rating', '!=', "")->get()->count();
        }
        $lessons = $course->courseTimeline()->orderby('sequence', 'asc')->get();


        if (\Auth::check()) {
            $completed_lessons = \Auth::user()->chapters()->where('course_id', $course->id)->get()->pluck('model_id')->toArray();
            $continue_course = $course->courseTimeline()->orderby('sequence', 'asc')->whereNotIn('model_id', $completed_lessons)->first();
            if ($continue_course == NULL) {
                $continue_course = $course->courseTimeline()->orderby('sequence', 'asc')->first();
            }
        }

        if ($course->courseTimeline) {

            $timeline = $course->courseTimeline()->orderBy('sequence')->get();
            foreach ($timeline as $item) {
                $completed = false;
                if (in_array($item->model_id, $completed_lessons)) {
                    $completed = true;
                }
                $type = 'lesson';
                $description = "";
                if ($item->model_type == 'App\Models\Test') {
                    $type = 'test';
                    $description = $item->model->description;
                } else {
                    $description = $item->model->short_text;
                }
                $course_timeline[] = [
                    'title' => $item->model->title,
                    'type' => $type,
                    'id' => $item->model_id,
                    'description' => $description,
                    'completed' => $completed,
                ];
            }
        }
        $mediaVideo = (!$course->mediaVideo) ? null : $course->mediaVideo->toArray();
        if($mediaVideo && $mediaVideo['type'] == 'embed'){
            preg_match('/src="([^"]+)"/', $mediaVideo['url'], $match);
            $url = $match[1];
            $mediaVideo['file_name'] = $url;
        }
        $result = [
            'course' => $course,
            'course_video' => $mediaVideo,
            'purchased_course' => $purchased_course,
            'course_rating' => $course_rating,
            'total_ratings' => $total_ratings,
            'is_reviewed' => $is_reviewed,
            'lessons' => $lessons,
            'course_timeline' => $course_timeline,
            'completed_lessons' => $completed_lessons,
            'continue_course' => $continue_course,
            'is_certified' => $course->isUserCertified(),
            'course_process' => $course->progress()
        ];
        return response()->json(['status' => 'success', 'result' => $result]);
    }


    /**
     * Submit review
     *
     * @return [json] Success message
     */
    public function submitReview(Request $request)
    {
        $reviewable_id = $request->item_id;
        if ($request->type == 'course') {
            $reviewable_type = Course::class;
            $item = Course::find($request->item_id);

        } else {
            $reviewable_type = Bundle::class;
            $item = Bundle::find($request->item_id);
        }
        if ($item != null) {
            $review = new Review();
            $review->user_id = auth()->user()->id;
            $review->reviewable_id = $reviewable_id;
            $review->reviewable_type = $reviewable_type;
            $review->rating = $request->rating;
            $review->content = $request->review;
            $review->save();
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failure']);
    }

    /**
     * Update Review
     *
     * @return [json] Success message
     */
    public function updateReview(Request $request)
    {
        $review = Review::where('id', '=', $request->review_id)->where('user_id', '=', auth()->user()->id)->first();
        if ($review != null) {
            $review->rating = $request->rating;
            $review->content = $request->review;
            $review->save();

            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failure']);
    }

    /**
     * Get Lesson
     *
     * @return [json] Success message
     */
    public function getLesson(Request $request)
    {
        $lesson = Lesson::where('published', '=', 1)
            ->where('id', '=', $request->lesson_id)
            ->first();
        if ($lesson != null) {
            $course = $lesson->course;
            $previous_lesson = $lesson->course->courseTimeline()->where('sequence', '<', $lesson->courseTimeline->sequence)
                ->orderBy('sequence', 'desc')
                ->first();
            $next_lesson = $lesson->course->courseTimeline()->where('sequence', '>', $lesson->courseTimeline->sequence)
                ->orderBy('sequence', 'asc')
                ->first();

            $is_certified = $lesson->course->isUserCertified();
            $course_progress = $lesson->course->progress();

            $downloadable_media = $lesson->downloadable_media;

            $mediaVideo = (!$lesson->mediaVideo) ? null : $lesson->mediaVideo->toArray();
            if($mediaVideo && $mediaVideo['type'] == 'embed'){
                preg_match('/src="([^"]+)"/', $mediaVideo['url'], $match);
                $url = $match[1];
                $mediaVideo['file_name'] = $url;
            }

            $video = $mediaVideo;
            $pdf = $lesson->mediaPDF;
            $audio = $lesson->mediaAudio;
            $lesson_media = [
                'downloadable_media' => $downloadable_media,
                'video' => $video,
                'pdf' => $pdf,
                'audio' => $audio,
            ];


            return response()->json(['status' => 'success', 'result' => ['lesson' => $lesson, 'lesson_media' => $lesson_media, 'previous_lesson' => $previous_lesson, 'next_lesson' => $next_lesson, 'is_certified' => $is_certified, 'course_progress' => $course_progress, 'course' => $course]]);
        }
        return response()->json(['status' => 'failure']);
    }


    /**
     * Get Test
     *
     * @return [json] Success message
     */

    public function getTest(Request $request){
        $test = Test::where('published', '=', 1)
            ->where('id', '=', $request->test_id)
            ->where('course_id', '=', $request->course_id)
            ->first();
        $questions = [];
        $is_test_given = false;
        //If Retest is being taken
        if(isset( $request->result_id)){
            $testResult = TestsResult::where('id', '=', $request->result_id)
                ->where('user_id', '=', auth()->user()->id)
                ->delete();
            $is_test_given = false;

        }


        if($test->questions && (count($test->questions) > 0)){
            foreach ($test->questions as $question){
                $options = [];
                if($question->options){
                    $options = $question->options->toArray();
                }

                $question_data['question'] = $question->toArray();
                $question_data['options'] = $options;

                $questions[] = $question_data;
            }
        }

        $test_result = TestsResult::where('test_id', $test->id)
            ->where('user_id', '=', auth()->user()->id)
            ->first();
        $result_data = NULL;
        if($test_result){
            $test_result = $test_result->toArray();
            $result = TestsResultsAnswer::where('tests_result_id','=',$test_result['id'])->get()->toArray();
            $is_test_given = true;
            $result_data = ['result_id' => $test_result['id'],'score' => $test_result,'answers' => $result];
        }

        $data['test'] = $test->toArray();
        $data['is_test_given'] = $is_test_given;
        $data['test_result'] = $result_data;
        return response()->json(['status' => 'success', 'response' => $data]);

    }


    /**
     * Save Test
     *
     * @return [json] Success message
     */
    public function saveTest(Request $request){
        $test = Test::where('id', $request->test_id)->firstOrFail();
        if(!$test){
            return response()->json(['status' => 'failure']);
        }
        $answers = [];
        $test_score = 0;
        foreach ($request->question_data as $item) {
            $question_id = $item['question_id'];
            $answer_id = $item['ans_id'];
            $question = Question::find($question_id);
            $correct = QuestionsOption::where('question_id', $question_id)
                    ->where('id', $answer_id)
                    ->where('correct', 1)->count() > 0;
            $answers[] = [
                'question_id' => $question_id,
                'option_id' => $answer_id,
                'correct' => $correct
            ];
            if ($correct) {
                if($question->score) {
                    $test_score += $question->score;
                }
            }
            /*
             * Save the answer
             * Check if it is correct and then add points
             * Save all test result and show the points
             */
        }
        $test_result = TestsResult::create([
            'test_id' => $test->id,
            'user_id' => \Auth::id(),
            'test_result' => $test_score,
        ]);
        $test_result->answers()->createMany($answers);


        if ($test->chapterStudents()->where('user_id', \Auth::id())->get()->count() == 0) {
            $test->chapterStudents()->create([
                'model_type' => $test->model_type,
                'model_id' => $test->id,
                'user_id' => auth()->user()->id,
                'course_id' => $test->course->id
            ]);
        }

        $result = TestsResultsAnswer::where('tests_result_id','=',$test_result->id)->get()->toArray();

        return response()->json(['status' => 'success','result_id' =>$test_result->id, 'score' => $test_score,'result' => $result]);

    }


    /**
     * Complete Lesson
     *
     * @return [json] Success message
     */
    public function courseProgress(Request $request)
    {

        if ($request->model_type == 'test') {
            $model_type = Test::class;
            $chapter = Test::find((int)$request->model_id);
        } else {
            $model_type = Lesson::class;
            $chapter = Lesson::find((int)$request->model_id);
        }
        if ($chapter != null) {
            if ($chapter->chapterStudents()->where('user_id', \Auth::id())->get()->count() == 0) {
                $chapter->chapterStudents()->create([
                    'model_type' => $model_type,
                    'model_id' => $request->model_id,
                    'user_id' => auth()->user()->id,
                    'course_id' => $chapter->course->id
                ]);
                return response()->json(['status' => 'success']);
            }
        }
        return response()->json(['status' => 'failure']);
    }

    /**
     * Save video progress for Lesson
     *
     * @return [json] Success message
     */
    public function videoProgress(Request $request)
    {
        $user = auth()->user();
        $video = Media::find($request->media_id);
        if ($video == null) {
            return response()->json(['status' => 'failure']);
        }
        $video_progress = VideoProgress::where('user_id', '=', $user->id)
            ->where('media_id', '=', $video->id)->first() ?: new VideoProgress();
        $video_progress->media_id = $video->id;
        $video_progress->user_id = $user->id;
        $video_progress->duration = $video_progress->duration ?: round($request->duration, 2);
        $video_progress->progress = round($request->progress, 2);
        if ($video_progress->duration - $video_progress->progress < 5) {
            $video_progress->progress = $video_progress->duration;
            $video_progress->complete = 1;
        }
        $video_progress->save();
        return response()->json(['status' => 'success']);
    }


    /**
     * Generate course certificate
     *
     * @return [json] Success message
     */

    public function generateCertificate(Request $request)
    {
        $course = Course::whereHas('students', function ($query) {
            $query->where('id', \Auth::id());
        })
            ->where('id', '=', $request->course_id)->first();
        if (($course != null) && ($course->progress() == 100)) {
            $certificate = Certificate::firstOrCreate([
                'user_id' => auth()->user()->id,
                'course_id' => $request->course_id
            ]);

            $data = [
                'name' => auth()->user()->name,
                'course_name' => $course->title,
                'date' => Carbon::now()->format('d M, Y'),
            ];
            $certificate_name = 'Certificate-' . $course->id . '-' . auth()->user()->id . '.pdf';
            $certificate->name = auth()->user()->id;
            $certificate->url = $certificate_name;
            $certificate->save();

            $pdf = \PDF::loadView('certificate.index', compact('data'))->setPaper('', 'landscape');

            $pdf->save(public_path('storage/certificates/' . $certificate_name));

            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failure']);
    }


    /**
     * Get Bundles
     *
     * @return [json] Bundle Object
     */
    public function getBundles(Request $request)
    {
        $types = ['popular', 'trending', 'featured'];
        $type = ($request->type) ? $request->type : null;
        if ($type != null) {
            if (in_array($type, $types)) {
                $bundles = Bundle::where('published', '=', 1)
                    ->where($type, '=', 1)
                    ->paginate(10);
            } else {
                return response()->json(['status' => 'failure', 'message' => 'Invalid Request']);
            }
        } else {
            $bundles = Bundle::where('published', '=', 1)
                ->paginate(10);
        }

        return response()->json(['status' => 'success', 'type' => $type, 'result' => $bundles]);

    }

    /**
     * Get Bundles
     *
     * @return [json] Bundle Object
     */
    public function getSingleBundle(Request $request)
    {
        $result['bundle'] = Bundle::where('published', '=', 1)
            ->where('id', '=', $request->bundle_id)
            ->first();

        $purchased_bundle = \Auth::check() &&  $result['bundle']->students()->where('user_id', \Auth::id())->count() > 0;


        if ($result['bundle'] == null) {
            return response()->json(['status' => 'failure', 'message' => 'Invalid Request']);
        }
        $result['courses'] = $result['bundle']->courses;
        return response()->json(['status' => 'success', 'purchased_bundle' => $purchased_bundle, 'result' => $result]);
    }


    /**
     * Add to cart
     *
     * @return [json] Return cart value
     */

    public function addToCart(Request $request)
    {
        $product = "";
        $teachers = "";
        $type = "";
        if ($request->type == 'course') {
            $product = Course::findOrFail($request->item_id);
            $teachers = $product->teachers->pluck('id', 'name');
            $type = 'course';

        } elseif ($request->type == 'bundle') {
            $product = Bundle::findOrFail($request->item_id);
            $teachers = $product->user->name;
            $type = 'bundle';
        }

        $cart_items = Cart::session(auth()->user()->id)->getContent()->keys()->toArray();
        if (!in_array($product->id, $cart_items)) {
            Cart::session(auth()->user()->id)
                ->add($product->id, $product->title, $product->price, 1,
                    [
                        'user_id' => auth()->user()->id,
                        'description' => $product->description,
                        'image' => $product->course_image,
                        'product_id' => $product->id,
                        'type' => $type,
                        'teachers' => $teachers
                    ]);
        }
        $this->applyTax('total');

        return response()->json(['status' => 'success']);
    }


    /**
     * Get Free Course / Bundle
     *
     * @return [json] Success Message
     */
    public function getNow(Request $request)
    {
        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->reference_no = str_random(8);
        $order->amount = 0;
        $order->status = 1;
        $order->payment_type = 0;
        $order->save();
        //Getting and Adding items
        if ($request->course_id) {
            $type = Course::class;
            $id = $request->course_id;
        } else {
            $type = Bundle::class;
            $id = $request->bundle_id;

        }
        $order->items()->create([
            'item_id' => $id,
            'item_type' => $type,
            'price' => 0
        ]);

        foreach ($order->items as $orderItem) {
            //Bundle Entries
            if ($orderItem->item_type == Bundle::class) {
                foreach ($orderItem->item->courses as $course) {
                    $course->students()->attach($order->user_id);
                }
            }
            $orderItem->item->students()->attach($order->user_id);
        }

        return response()->json(['status' => 'success']);

    }


    /**
     * Remove from cart
     *
     * @return [json] Remove from cart
     */
    public function removeFromCart(Request $request)
    {

        foreach (Cart::session(auth()->user()->id)->getContent() as $cartItem) {
            if (($cartItem->attributes->type == $request->type) && ($cartItem->attributes->product_id == $request->item_id)) {
                Cart::session(auth()->user()->id)->remove($request->item_id);
            }
        }
        return response()->json(['status' => 'success']);
    }


    /**
     * Show Cart
     *
     * @return [json] Get Cart data
     */
    public function getCartData(Request $request)
    {
        $course_ids = [];
        $bundle_ids = [];
        $couponArray = [];
        if (count(Cart::session(auth()->user()->id)->getContent()) > 0) {
            foreach (Cart::session(auth()->user()->id)->getContent() as $item) {
                if ($item->attributes->type == 'bundle') {
                    $bundle_ids[] = $item->id;
                } else {
                    $course_ids[] = $item->id;
                }
            }
            $courses = Course::find($course_ids);
            $bundles = Bundle::find($bundle_ids);
            $bundlesData = Bundle::find($bundle_ids);

            $coursesData = $bundlesData->merge($courses);
            $total = $coursesData->sum('price');
            $subtotal = $total;

            if (count(Cart::getConditionsByType('coupon')) > 0) {
                $coupon = Cart::getConditionsByType('coupon')->first();
                $couponData = Coupon::where('code', '=', $coupon->getName())->first();
                $couponArray = [
                    'name' => $couponData->name,
                    'code' => $couponData->code,
                    'type' => ($couponData->type == 1) ? trans('labels.backend.coupons.discount_rate') : trans('labels.backend.coupons.flat_rate'),
                    'value' => $coupon->getValue(),
                    'amount' => number_format($coupon->getCalculatedValue($total), 2)
                ];
            }

            $taxes = Tax::where('status', '=', 1)->get();
            $taxData = [];
            if ($taxes != null) {
                foreach ($taxes as $tax) {
                    $total = Cart::session(auth()->user()->id)->getTotal();
                    $amount = number_format($total * $tax->rate / 100, 2);
                    $taxData[] = ['name' => '+' . $tax->rate . '% ' . $tax->name, 'amount' => $amount];
                }
            }

            $total = Cart::session(auth()->user()->id)->getTotal();


            return response()->json(['status' => 'success', 'result' => ['courses' => $courses, 'bundles' => $bundles, 'coupon' => $couponArray, 'tax' => $taxData, 'subtotal' => $subtotal, 'total' => $total]]);
        }
        return response()->json(['status' => 'failure']);
    }

    /**
     * Clear Cart
     *
     * @return [json] Success Message
     */
    public function clearCart()
    {
        Cart::session(auth()->user()->id)->clear();
        return response()->json(['status' => 'success']);
    }


    /**
     * Payment Status
     *
     * @return [json] Success Message
     */
    public function paymentStatus(Request $request)
    {
        $counter = 0;
        $items = [];
        $order = Order::where('id', '=', (int)$request->order_id)->where('status', '=', 0)->first();
        if ($order) {
            $order->payment_type = $request->payment_type;
            $order->status = ($request->status == 'success') ? 1 : 0;
            $order->remarks = $request->remarks;
            $order->transaction_id = $request->transaction_id;
            $order->save();
            if($order->status == 1){
                (new EarningHelper())->insert($order);
            }
            if ((int)$request->payment_type == 3) {
                foreach ($order->items as $key => $cartItem) {
                    $counter++;
                    array_push($items, ['number' => $counter, 'name' => $cartItem->item->name, 'price' => $cartItem->item->price]);
                }

                $content['items'] = $items;
                $content['total'] = $order->amount;
                $content['reference_no'] = $order->reference_no;

                try {
                    \Mail::to(auth()->user()->email)->send(new OfflineOrderMail($content));
                } catch (\Exception $e) {
                    \Log::info($e->getMessage() . ' for order ' . $order->id);
                }

            } else {
                foreach ($order->items as $orderItem) {
                    //Bundle Entries
                    if ($orderItem->item_type == Bundle::class) {
                        foreach ($orderItem->item->courses as $course) {
                            $course->students()->attach($order->user_id);
                        }
                    }
                    $orderItem->item->students()->attach($order->user_id);
                }

                //Generating Invoice
                generateInvoice($order);
            }

            return response()->json(['status' => 'success']);
        } else {
            return response()->json(['status' => 'failure', 'message' => 'No order found']);

        }

    }


    /**
     * Create Order
     *
     * @return [json] Order
     */
    private function makeOrderOld()
    {
        $coupon = Cart::session(auth()->user()->id)->getConditionsByType('coupon')->first();
        if ($coupon != null) {
            $coupon = Coupon::where('code', '=', $coupon->getName())->first();
        }
        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->reference_no = str_random(8);
        $order->amount = Cart::session(auth()->user()->id)->getTotal();
        $order->status = 1;
        $order->coupon_id = ($coupon == null) ? 0 : $coupon->id;
        $order->payment_type = 3;
        $order->save();
        //Getting and Adding items
        foreach (Cart::session(auth()->user()->id)->getContent() as $cartItem) {
            if ($cartItem->attributes->type == 'bundle') {
                $type = Bundle::class;
            } else {
                $type = Course::class;
            }
            $order->items()->create([
                'item_id' => $cartItem->id,
                'item_type' => $type,
                'price' => $cartItem->price
            ]);
        }
        Cart::session(auth()->user()->id)->removeConditionsByType('coupon');

        return $order;
    }

    private function makeOrder($data)
    {
        $coupon = $data['coupon_data'];
        if ($coupon != false) {
            $coupon_id = $coupon['id'];
        } else {
            $coupon_id = 0;
        }
        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->reference_no = str_random(8);
        $order->amount = $data['final_total'];
        $order->status = 0;
        $order->coupon_id = $coupon_id;
        $order->payment_type = 0;
        $order->save();
        //Getting and Adding items
        foreach ($data['data'] as $item) {
            if ($item['type'] == 'bundle') {
                $type = Bundle::class;
            } else {
                $type = Course::class;
            }
            $order->items()->create([
                'item_id' => $item['id'],
                'item_type' => $type,
                'price' => $item['price']
            ]);
        }

        return $order;
    }


    /**
     * Create Order
     *
     * @return [json] Order
     */
    public function getBlog(Request $request)
    {

        if ($request->blog_id != null) {
            $blog_id = $request->blog_id;
            $blog = Blog::with('comments', 'category', 'author')->find($blog_id);
            // get previous user id
            $previous_id = Blog::where('id', '<', $blog_id)->max('id');
            $previous = Blog::find($previous_id);

            // get next user id
            $next_id = Blog::where('id', '>', $blog_id)->min('id');
            $next = Blog::find($next_id);

            return response()->json(['status' => 'success', 'blog' => $blog, 'next' => $next_id, 'previous' => $previous_id]);
        }


        $blog = Blog::has('category')->with('comments')->OrderBy('created_at', 'desc')->paginate(10);
        return response()->json(['status' => 'success', 'blog' => $blog]);

    }


    /**
     * Blog By Category
     *
     * @return [json] Blog List
     */
    public function getBlogByCategory(Request $request)
    {
        $category = Category::find((int)$request->category_id);
        if ($category != null) {
            $blog = $category->blogs()->paginate(10);
            return response()->json(['status' => 'success', 'result' => $blog]);
        }
        return response()->json(['status' => 'failure']);

    }


    /**
     * Blog By Tag
     *
     * @return [json] Blog List
     */
    public function getBlogByTag(Request $request)
    {
        $tag = Tag::find((int)$request->tag_id);
        if ($tag != "") {
            $blog = $tag->blogs()->paginate(10);
            return response()->json(['status' => 'success', 'result' => $blog]);
        }
        return response()->json(['status' => 'failure']);
    }


    /**
     * Blog Store Comment
     *
     * @return [json] Success Message
     */
    public function addBlogComment(Request $request)
    {
        $this->validate($request, [
            'comment' => 'required|min:3',
        ]);
        $blog = Blog::find($request->blog_id);
        if ($blog != null) {
            $blogcooment = new BlogComment($request->all());
            $blogcooment->name = auth()->user()->full_name;
            $blogcooment->email = auth()->user()->email;
            $blogcooment->comment = $request->comment;
            $blogcooment->blog_id = $blog->id;
            $blogcooment->user_id = auth()->user()->id;
            $blogcooment->save();
            return response()->json(['status' => 'success']);

        }

        return response()->json(['status' => 'failure']);
    }


    /**
     * Blog Delete Comment
     *
     * @return [json] Success Message
     */
    public function deleteBlogComment(Request $request)
    {
        $comment = BlogComment::find((int)$request->comment_id);
        if (auth()->user()->id == $comment->user_id) {
            $comment->delete();
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failure']);
    }


    /**
     * Forums home
     *
     * @return [json] forum object
     */

    public function getForum(Request $request)
    {

        $pagination_results = config('chatter.paginate.num_of_results');

        $discussions = Models::discussion()->with('user')->with(['post','post.user'])->with('postsCount')->with('category')->orderBy(config('chatter.order_by.discussions.order'), config('chatter.order_by.discussions.by'));
        if (isset($slug)) {
            $category = Models::category()->where('slug', '=', $slug)->first();

            if (isset($category->id)) {
                $current_category_id = $category->id;
                $discussions = $discussions->where('chatter_category_id', '=', $category->id);
            } else {
                $current_category_id = null;
            }
        }

        $discussions = $discussions->paginate($pagination_results);

        $categories = Models::category()->get();

        $result = [
            'discussions' => $discussions,
            'categories' => $categories,
        ];

        return response()->json(['status' => 'success', 'result' => $result]);

    }

    /**
     * Create Discussion
     *
     * @return [json] success message
     */

    public function createDiscussion(Request $request)
    {
        $request->request->add(['body_content' => strip_tags($request->body)]);

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:5|max:255',
            'body_content' => 'required|min:10',
            'chatter_category_id' => 'required',
        ], [
            'title.required' => trans('chatter::alert.danger.reason.title_required'),
            'title.min' => [
                'string' => trans('chatter::alert.danger.reason.title_min'),
            ],
            'title.max' => [
                'string' => trans('chatter::alert.danger.reason.title_max'),
            ],
            'body_content.required' => trans('chatter::alert.danger.reason.content_required'),
            'body_content.min' => trans('chatter::alert.danger.reason.content_min'),
            'chatter_category_id.required' => trans('chatter::alert.danger.reason.category_required'),
        ]);


        Event::fire(new ChatterBeforeNewDiscussion($request, $validator));
        if (function_exists('chatter_before_new_discussion')) {
            chatter_before_new_discussion($request, $validator);
        }

        $user_id = Auth::user()->id;

        if (config('chatter.security.limit_time_between_posts')) {
            if ($this->notEnoughTimeBetweenDiscussion()) {
                $minutes = trans_choice('chatter::messages.words.minutes', config('chatter.security.time_between_posts'));

                return response()->json(['status' => 'failure', 'result' => trans('chatter::alert.danger.reason.prevent_spam', [
                    'minutes' => $minutes,
                ])]);
            }
        }

        // *** Let's gaurantee that we always have a generic slug *** //
        $slug = str_slug($request->title, '-');

        $discussion_exists = Models::discussion()->where('slug', '=', $slug)->withTrashed()->first();
        $incrementer = 1;
        $new_slug = $slug;
        while (isset($discussion_exists->id)) {
            $new_slug = $slug . '-' . $incrementer;
            $discussion_exists = Models::discussion()->where('slug', '=', $new_slug)->withTrashed()->first();
            $incrementer += 1;
        }

        if ($slug != $new_slug) {
            $slug = $new_slug;
        }

        $new_discussion = [
            'title' => $request->title,
            'chatter_category_id' => $request->chatter_category_id,
            'user_id' => $user_id,
            'slug' => $slug,
            'color' => '#0c0919',
        ];

        $category = Models::category()->find($request->chatter_category_id);
        if (!isset($category->slug)) {
            $category = Models::category()->first();
        }

        $discussion = Models::discussion()->create($new_discussion);

        $new_post = [
            'chatter_discussion_id' => $discussion->id,
            'user_id' => $user_id,
            'body' => $request->body,
        ];

        if (config('chatter.editor') == 'simplemde'):
            $new_post['markdown'] = 1;
        endif;

        // add the user to automatically be notified when new posts are submitted
        $discussion->users()->attach($user_id);

        $post = Models::post()->create($new_post);

        if ($post->id) {
            Event::fire(new ChatterAfterNewDiscussion($request, $discussion, $post));
            if (function_exists('chatter_after_new_discussion')) {
                chatter_after_new_discussion($request);
            }

            return response()->json(['status' => 'success']);

        } else {
            return response()->json(['status' => 'failure', 'result' => 'Not found']);


        }
    }


    /**
     * Create Response for Discussion
     *
     * @return [json] success message
     */
    public function storeResponse(Request $request)
    {
        $stripped_tags_body = ['body' => strip_tags($request->body)];
        $validator = Validator::make($stripped_tags_body, [
            'body' => 'required|min:10',
        ], [
            'body.required' => trans('chatter::alert.danger.reason.content_required'),
            'body.min' => trans('chatter::alert.danger.reason.content_min'),
        ]);

        Event::fire(new ChatterBeforeNewResponse($request, $validator));
        if (function_exists('chatter_before_new_response')) {
            chatter_before_new_response($request, $validator);
        }

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }


        $request->request->add(['user_id' => Auth::user()->id]);

        if (config('chatter.editor') == 'simplemde'):
            $request->request->add(['markdown' => 1]);
        endif;

        $new_post = Models::post()->create($request->all());

        $discussion = Models::discussion()->find($request->chatter_discussion_id);

        $category = Models::category()->find($discussion->chatter_category_id);
        if (!isset($category->slug)) {
            $category = Models::category()->first();
        }

        if ($new_post->id) {
            $discussion->last_reply_at = $discussion->freshTimestamp();
            $discussion->save();

            Event::fire(new ChatterAfterNewResponse($request, $new_post));
            if (function_exists('chatter_after_new_response')) {
                chatter_after_new_response($request);
            }

            // if email notifications are enabled
            if (config('chatter.email.enabled')) {
                // Send email notifications about new post
                $this->sendEmailNotifications($new_post->discussion);
            }


            return response()->json(['status' => 'success', 'message' => trans('chatter::alert.success.reason.submitted_to_post')]);


        } else {
            return response()->json(['status' => 'failure', 'message' => trans('chatter::alert.danger.reason.trouble')]);
        }
    }


    /**
     * Update the Response.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return [json] success message
     */
    public function updateResponse(Request $request)
    {
        $id = $request->post_id;
        $stripped_tags_body = ['body' => strip_tags($request->body)];
        $validator = Validator::make($stripped_tags_body, [
            'body' => 'required|min:10',
        ], [
            'body.required' => trans('chatter::alert.danger.reason.content_required'),
            'body.min' => trans('chatter::alert.danger.reason.content_min'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $post = Models::post()->find($id);
        if (!Auth::guest() && (Auth::user()->id == $post->user_id)) {
            if ($post->markdown) {
                $post->body = strip_tags($request->body);
            } else {
                $post->body = Purifier::clean($request->body);
            }
            $post->save();

            $discussion = Models::discussion()->find($post->chatter_discussion_id);

            $category = Models::category()->find($discussion->chatter_category_id);
            if (!isset($category->slug)) {
                $category = Models::category()->first();
            }

            return response()->json(['status' => 'success', 'message' => trans('chatter::alert.success.reason.updated_post')]);

        } else {

            return response()->json(['status' => 'failure', 'message' => trans('chatter::alert.danger.reason.update_post')]);
        }
    }

    /**
     * Delete Response.
     *
     * @param string $id
     * @param  \Illuminate\Http\Request
     *
     * @return [json] success message
     */
    public function deleteResponse(Request $request)
    {
        $id = $request->post_id;

        $post = Models::post()->with('discussion')->findOrFail($id);

        if ($request->user()->id !== (int)$post->user_id) {

            return response()->json(['status' => 'failure', 'message' => trans('chatter::alert.danger.reason.destroy_post')]);
        }

        if ($post->discussion->posts()->oldest()->first()->id === $post->id) {
            if (config('chatter.soft_deletes')) {
                $post->discussion->posts()->delete();
                $post->discussion()->delete();
            } else {
                $post->discussion->posts()->forceDelete();
                $post->discussion()->forceDelete();
            }

            return response()->json(['status' => 'success', 'message' => trans('chatter::alert.success.reason.destroy_post')]);
        }

        $post->delete();

        return response()->json(['status' => 'success', 'message' => trans('chatter::alert.success.reason.destroy_from_discussion')]);
    }


    /**
     * Get Conversations.
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] messages
     */

    public function getMessages(Request $request)
    {
        $thread = "";

        $teachers = User::role('teacher')->select('id','first_name','last_name')->get();

        auth()->user()->load('threads.messages.sender');

        $unreadThreads = [];
        $threads = [];
        foreach (auth()->user()->threads as $item) {
            if ($item->unreadMessagesCount > 0) {
                $unreadThreads[] = $item;
            } else {
                $threads[] = $item;
            }
        }
        $threads = Collection::make(array_merge($unreadThreads, $threads));

        if (request()->has('thread') && ($request->thread != null)) {

            if (request('thread')) {
                $thread = auth()->user()->threads()
                    ->where('message_threads.id', '=', $request->thread)
                    ->first();
                if ($thread == "") {
                    return response()->json(['status' => 'failure', 'Not found']);
                }
                //Read Thread
                auth()->user()->markThreadAsRead($thread->id);
            }
        }


        return response()->json(['status' => 'success', 'threads' => $threads,
            'teachers' => $teachers,
            'thread' => $thread]);

    }


    /**
     * Create Message
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Success Message
     */
    public function composeMessage(Request $request)
    {
        $recipients = $request->data['recipients'];
        $message = $request->data['message'];


        $message = Messenger::from(auth()->user())->to($recipients)->message($message)->send();
        return response()->json(['status' => 'success', 'thread' => $message->thread_id]);
    }


    /**
     * Reply Message
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Success Message
     */
    public function replyMessage(Request $request)
    {

        $thread = auth()->user()->threads()
            ->where('message_threads.id', '=', $request->thread_id)
            ->first();
        $message = Messenger::from(auth()->user())->to($thread)->message($request->message)->send();
        return response()->json(['status' => 'success', 'thread' => $message->thread_id]);

    }

    /**
     * Get Unread Messages
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Success Message
     */
    public function getUnreadMessages(Request $request)
    {
        $unreadMessageCount = auth()->user()->unreadMessagesCount;
        $unreadThreads = [];
        foreach (auth()->user()->threads as $item) {
            if ($item->unreadMessagesCount > 0) {
                $data = [
                    'thread_id' => $item->id,
                    'message' => str_limit($item->lastMessage->body, 35),
                    'unreadMessagesCount' => $item->unreadMessagesCount,
                    'title' => $item->title
                ];
                $unreadThreads[] = $data;
            }
        }
        return response()->json(['status' => 'success', 'unreadMessageCount' => $unreadMessageCount, 'threads' => $unreadThreads]);
    }


    /**
     * Get My Certificates
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] certificates object
     */
    public function getMyCertificates()
    {
        $certificates = auth()->user()->certificates;

        return response()->json(['status' => 'success', 'result' => $certificates]);
    }


    /**
     * Get My Courses / Bundles / Purchases
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] certificates object
     */
    public function getMyPurchases()
    {
        $purchased_courses = auth()->user()->purchasedCourses();
        $purchased_bundles = auth()->user()->purchasedBundles();

        return response()->json(['status' => 'success', 'result' => ['courses' => $purchased_courses, 'bundles' => $purchased_bundles]]);
    }


    /**
     * Get My Account
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Loggedin user object
     */
    public function getMyAccount()
    {
        $user = auth()->user();
        return response()->json(['status' => 'success', 'result' => $user]);
    }


    /**
     * Update My Account
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Update account
     */
    public function updateMyAccount(Request $request)
    {
        $fieldsList = [];
        if (config('registration_fields') != NULL) {
            $fields = json_decode(config('registration_fields'));

            foreach ($fields as $field) {
                $fieldsList[] = '' . $field->name;
            }
        }
        $output = $this->userRepository->update(
            $request->user()->id,
            $request->only('first_name', 'last_name', 'dob', 'phone', 'gender', 'address', 'city', 'pincode', 'state', 'country', 'avatar_type', 'avatar_location'),
            $request->has('avatar_location') ? $request->file('avatar_location') : false
        );

        // E-mail address was updated, user has to reconfirm
        if (is_array($output) && $output['email_changed']) {
            auth()->logout();

            return response()->json(['status' => 'success', 'message' => __('strings.frontend.user.email_changed_notice')]);
        }

        return response()->json(['status' => 'success', 'message' => __('strings.frontend.user.profile_updated')]);

    }

    /**
     * Update Password
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Update password
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        if (Hash::check($request->old_password, $user->password)) {
            $user->update(['password' => $request->password]);
        }
        return response()->json(['status' => 'success', 'message' => __('strings.frontend.user.password_updated')]);

    }


    /**
     * Update Pages (About-us)
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] Update password
     */
    public function getPage()
    {
        $page = Page::where('slug', '=', request('page'))
            ->where('published', '=', 1)->first();
        if ($page != "") {
            return response()->json(['status' => 'success', 'result' => $page]);
        }
        return response()->json(['status' => 'failure', 'result' => NULL]);

    }


    /**
     * Subscribe newsletter
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] response
     */

    public function subscribeNewsletter(Request $request)
    {
        if (config('mail_provider') != NULL && config('mail_provider') == "mailchimp") {
            try {
                if (!Newsletter::isSubscribed($request->email)) {
                    if (config('mailchimp_double_opt_in')) {
                        Newsletter::subscribePending($request->email);
                        $message = "We've sent you an email, Check your mailbox for further procedure.";
                    } else {
                        Newsletter::subscribe($request->email);
                        $message = "You've subscribed successfully";
                    }
                    return response()->json(['status' => 'success', 'message' => $message]);
                } else {
                    $message = "Email already exist in subscription list";
                    return response()->json(['status' => 'failure', 'message' => $message]);

                }
            } catch (\Exception $e) {
                \Log::info($e->getMessage());
                $message = "Something went wrong, Please try again Later";
                return response()->json(['status' => 'failure', 'message' => $message]);
            }

        } elseif (config('mail_provider') != NULL && config('mail_provider') == "sendgrid") {
            try {
                $apiKey = config('sendgrid_api_key');
                $sg = new \SendGrid($apiKey);
                $query_params = json_decode('{"page": 1, "page_size": 1}');
                $response = $sg->client->contactdb()->recipients()->get(null, $query_params);
                if ($response->statusCode() == 200) {
                    $users = json_decode($response->body());
                    $emails = [];
                    foreach ($users->recipients as $user) {
                        array_push($emails, $user->email);
                    }
                    if (in_array($request->email, $emails)) {
                        $message = "Email already exist in subscription list";
                        return response()->json(['status' => 'failure', 'message' => $message]);
                    } else {
                        $request_body = json_decode(
                            '[{
                             "email": "' . $request->email . '",
                             "first_name": "",
                             "last_name": ""
                              }]'
                        );
                        $response = $sg->client->contactdb()->recipients()->post($request_body);
                        if ($response->statusCode() != 201 || (json_decode($response->body())->new_count == 0)) {

                            $message = "Email already exist in subscription list";
                            return response()->json(['status' => 'failure', 'message' => $message]);
                        } else {
                            $recipient_id = json_decode($response->body())->persisted_recipients[0];
                            $list_id = config('sendgrid_list');
                            $response = $sg->client->contactdb()->lists()->_($list_id)->recipients()->_($recipient_id)->post();
                            if ($response->statusCode() == 201) {
                                session()->flash('alert', "You've subscribed successfully");
                            } else {
                                $message = "Check your email and try again";
                                return response()->json(['status' => 'failure', 'message' => $message]);
                            }

                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::info($e->getMessage());
                $message = "Something went wrong, Please try again Later";
                return response()->json(['status' => 'failure', 'message' => $message]);
            }
        }
        return response()->json(['status' => 'failure', 'message' => 'Please setup mail provider in Admin dashboard on server']);


    }


    /**
     * Get Offers
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] response
     */
    public function getOffers()
    {
        $coupons = Coupon::where('status', '=', 1)->get();
        return ['status' => 'success', 'coupons' => $coupons];
    }


    /**
     * Apply Coupon
     *
     * @param  \Illuminate\Http\Request
     *
     * @return [json] response
     */
    public function applyCouponOld(Request $request)
    {
        Cart::session(auth()->user()->id)->removeConditionsByType('coupon');

        $coupon = $request->coupon;
        $coupon = Coupon::where('code', '=', $coupon)
            ->where('status', '=', 1)
            ->first();
        if ($coupon != null) {
            Cart::session(auth()->user()->id)->clearCartConditions();
            Cart::session(auth()->user()->id)->removeConditionsByType('coupon');
            Cart::session(auth()->user()->id)->removeConditionsByType('tax');

            $ids = Cart::session(auth()->user()->id)->getContent()->keys();
            $course_ids = [];
            $bundle_ids = [];
            foreach (Cart::session(auth()->user()->id)->getContent() as $item) {
                if ($item->attributes->type == 'bundle') {
                    $bundle_ids[] = $item->id;
                } else {
                    $course_ids[] = $item->id;
                }
            }
            $courses = new Collection(Course::find($course_ids));
            $bundles = Bundle::find($bundle_ids);
            $courses = $bundles->merge($courses);

            $total = $courses->sum('price');
            $isCouponValid = false;

            if ($coupon->per_user_limit > $coupon->useByUser()) {
                $isCouponValid = true;
                if (($coupon->min_price != null) && ($coupon->min_price > 0)) {
                    if ($total >= $coupon->min_price) {
                        $isCouponValid = true;
                    }
                } else {
                    $isCouponValid = true;
                }
            }

            if ($coupon->expires_at != null) {
                if (Carbon::parse($coupon->expires_at) >= Carbon::now()) {
                    $isCouponValid = true;
                } else {
                    $isCouponValid = false;
                }
            }

            if ($isCouponValid == true) {
                $type = null;
                if ($coupon->type == 1) {
                    $type = '-' . $coupon->amount . '%';
                } else {
                    $type = '-' . $coupon->amount;
                }

                $condition = new \Darryldecode\Cart\CartCondition(array(
                    'name' => $coupon->code,
                    'type' => 'coupon',
                    'target' => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                    'value' => $type,
                    'order' => 1
                ));

                Cart::session(auth()->user()->id)->condition($condition);
                //Apply Tax
                $taxData = $this->applyTax('subtotal');


                return ['status' => 'success'];
            }


        }
        return ['status' => 'failure', 'message' => trans('labels.frontend.cart.invalid_coupon')];
    }

    public function applyCoupon(Request $request)
    {
        $data = [];
        $items = [];
        $total = 0;
        $coupon = $request->coupon;
        $coupon = Coupon::where('code', '=', $coupon)
            ->where('status', '=', 1)
            ->first();
        $isCouponValid = false;
        if ($coupon != null) {

            if (count($request->data) > 0) {
                foreach ($request->data as $item) {
                    $id = $item['id'];
                    $price = $item['price'];
                    if ($item['type'] == 'bundle') {
                        $status = false;
                        $bundle = Bundle::where('id', '=', $item['id'])
                            ->where('price', '=', $item['price'])
                            ->where('published', '=', 1)
                            ->first();
                        if ($bundle) {
                            $status = true;
                            $total = $total + $bundle->price;
                        }
                        $bundle = [
                            'id' => $id,
                            'type' => 'bundle',
                            'price' => $price,
                            'status' => $status
                        ];
                        array_push($items, $bundle);

                    } else {
                        $status = false;

                        $course = Course::where('id', '=', $id)
                            ->where('price', '=', $price)
                            ->where('published', '=', 1)
                            ->first();
                        if ($course) {
                            $status = true;
                            $total = $total + $course->price;

                        }
                        $course = [
                            'id' => $id,
                            'type' => 'course',
                            'price' => $price,
                            'status' => $status
                        ];
                        array_push($items, $course);

                    }
                }
                $data['data'] = $items;

                $total = (float)number_format($total, 2);

                if ((float)$request->total == $total) {

                    if ($coupon->per_user_limit > $coupon->useByUser()) {
                        $isCouponValid = true;
                        if (($coupon->min_price != null) && ($coupon->min_price > 0)) {
                            if ($total >= $coupon->min_price) {
                                $isCouponValid = true;
                            }
                        } else {
                            $isCouponValid = true;
                        }
                    }

                    if ($coupon->expires_at != null) {
                        if (Carbon::parse($coupon->expires_at) >= Carbon::now()) {
                            $isCouponValid = true;
                        } else {
                            $isCouponValid = false;
                        }
                    }

                    if ($isCouponValid == true) {

                        $type = null;
                        if ($coupon->type == 1) {
                            $discount = $total * $coupon->amount / 100;

                        } else {
                            $discount = $coupon->amount;

                        }
                        $data['subtotal'] = (float)number_format($total, 2);

                        //$data['discounted_total'] = (float)number_format($total - $discount,2);
                        $data['coupon_data'] = $coupon->toArray();
                        $data['coupon_data']['total_coupon_discount'] = (float)number_format($discount, 2);


                        //Apply Tax
                        $data['tax_data'] = $this->applyTax($total);
                        $tax_amount = $data['tax_data']['total_tax'];

                        $discount = $data['coupon_data']['total_coupon_discount'];
                        $data['final_total'] = ($total - $discount) + $tax_amount;


                        return ['status' => 'success', 'result' => $data];
                    } else {
                        return ['status' => 'failure', 'message' => 'Coupon is Invalid'];
                    }


                } else {
                    return ['status' => 'failure', 'message' => 'Total Mismatch', 'result' => $data];

                }
            }
            return ['status' => 'failure', 'message' => 'Add Items to Cart before applying coupon'];

        }
        return ['status' => 'failure', 'message' => 'Please input valid coupon'];


    }


    public function orderConfirmation(Request $request)
    {
        $data = [];
        $items = [];
        $total = 0;
        if (count($request->data) > 0) {
            foreach ($request->data as $item) {
                $id = $item['id'];
                $price = $item['price'];
                if ($item['type'] == 'bundle') {
                    $status = false;
                    $bundle = Bundle::where('id', '=', $item['id'])
                        ->where('price', '=', $item['price'])
                        ->where('published', '=', 1)
                        ->first();
                    if ($bundle) {
                        $status = true;
                        $total = $total + $bundle->price;
                    }
                    $bundle = [
                        'id' => $id,
                        'type' => 'bundle',
                        'price' => $price,
                        'status' => $status
                    ];
                    array_push($items, $bundle);

                } else {
                    $status = false;

                    $course = Course::where('id', '=', $id)
                        ->where('price', '=', $price)
                        ->where('published', '=', 1)
                        ->first();
                    if ($course) {
                        $status = true;
                        $total = $total + $course->price;

                    }
                    $course = [
                        'id' => $id,
                        'type' => 'course',
                        'price' => $price,
                        'status' => $status
                    ];
                    array_push($items, $course);

                }
            }
            $data['data'] = $items;

            if ((float)$request->total == floatval($total)) {

                $coupon = $request->coupon;
                $discount = 0;
                $tax_amount = 0;
                $coupon = Coupon::where('code', '=', $coupon)
                    ->where('status', '=', 1)
                    ->first();

                $type = null;
                if ($coupon) {
                    if ($coupon->type == 1) {
                        $discount = $total * $coupon->amount / 100;

                    } else {
                        $discount = $coupon->amount;

                    }
                    //$data['discounted_total'] = (float)number_format($total - $discount,2);
                    $data['coupon_data'] = $coupon->toArray();
                    $data['coupon_data']['total_coupon_discount'] = (float)number_format($discount, 2);
                    $discount = $data['coupon_data']['total_coupon_discount'];


                } else {
                    $data['coupon_data'] = false;
                }


                $data['subtotal'] = (float)$total;
                $total = $total - $discount;

                //Apply Tax
                $data['tax_data'] = $this->applyTax($total);
                if ($data['tax_data'] != 0) {
                    $tax_amount = $data['tax_data']['total_tax'];
                }

                $data['final_total'] = $total + $tax_amount;

                $order = $this->makeOrder($data);
                $data['order'] = $order;

                return $data;

            } else {
                return ['status' => 'failure', 'message' => 'Total Mismatch', 'result' => $data];

            }
        }
        return ['status' => 'failure', 'message' => 'Add Items to Cart before applying coupon'];
    }

    public function removeCoupon(Request $request)
    {//Obsolete

        Cart::session(auth()->user()->id)->clearCartConditions();
        Cart::session(auth()->user()->id)->removeConditionsByType('coupon');
        Cart::session(auth()->user()->id)->removeConditionsByType('tax');

        $course_ids = [];
        $bundle_ids = [];
        foreach (Cart::session(auth()->user()->id)->getContent() as $item) {
            if ($item->attributes->type == 'bundle') {
                $bundle_ids[] = $item->id;
            } else {
                $course_ids[] = $item->id;
            }
        }
        $courses = new Collection(Course::find($course_ids));
        $bundles = Bundle::find($bundle_ids);
        $courses = $bundles->merge($courses);

        //Apply Tax
        $this->applyTax('subtotal');

        return ['status' => 'success'];

    }

    private function notEnoughTimeBetweenDiscussion()
    {
        $user = Auth::user();

        $past = Carbon::now()->subMinutes(config('chatter.security.time_between_posts'));

        $last_discussion = Models::discussion()->where('user_id', '=', $user->id)->where('created_at', '>=', $past)->first();

        if (isset($last_discussion)) {
            return true;
        }

        return false;
    }

    private function sendEmailNotifications($discussion)
    {
        $users = $discussion->users->except(Auth::user()->id);
        foreach ($users as $user) {
            \Mail::to($user)->queue(new ChatterDiscussionUpdated($discussion));
        }
    }

    public function getConfigs()
    {
        $currency = getCurrency(config('app.currency'));
        return response()->json(['status' => 'success', 'result' => $currency]);
    }

    private function applyTax($total)
    {
        //Apply Conditions on Cart
        $taxes = Tax::where('status', '=', 1)->get();
        if (count($taxes) > 0) {
            $taxData = [];
            $taxDetails = [];
            $amounts = [];
            foreach ($taxes as $tax) {
                $amount = $total * ((float)$tax->rate / 100);
                $amounts[] = $amount;
                $taxMeta = [
                    'name' => (float)$tax->rate . '% ' . $tax->name,
                    'amount' => (float)$amount
                ];
                array_push($taxDetails, $taxMeta);
            }
            $taxData['taxes'] = $taxDetails;
            $taxData['total_tax'] = array_sum($amounts);

            return $taxData;
        }
        return false;
    }
}
