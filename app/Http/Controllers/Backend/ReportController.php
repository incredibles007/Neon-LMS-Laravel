<?php

namespace App\Http\Controllers\Backend;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getSalesReport()
    {

        $courses = Course::ofTeacher()->pluck('id');
        $bundles = Bundle::ofTeacher()->pluck('id');


        $bundle_earnings = Order::with('items')->whereHas('items',function ($q) use ($bundles){
            $q->where('item_type','=',Bundle::class)
                ->whereIn('item_id',$bundles);
        })->where('status','=',1);


        $bundle_sales = $bundle_earnings->count();
        $bundle_earnings = $bundle_earnings->sum('amount');

        $course_earnings = Order::with('items')->whereHas('items',function ($q) use ($courses){
            $q->where('item_type','=',Course::class)
                ->whereIn('item_id',$courses);
        })->where('status','=',1);

        $course_sales = $course_earnings->count();
        $course_earnings = $course_earnings->sum('amount');

        $total_earnings = $course_earnings+$bundle_earnings;
        $total_sales = $course_sales+$bundle_sales;

        return view('backend.reports.sales',compact('total_earnings','total_sales'));
    }

    public function getStudentsReport()
    {
        return view('backend.reports.students');
    }

    public function getCourseData(Request $request)
    {

        $courses = Course::ofTeacher()->pluck('id');

        $course_orders = OrderItem::whereHas('order',function ($q){
            $q->where('status','=',1);
        })->where('item_type','=',Course::class)
            ->whereIn('item_id',$courses)
            ->join('courses', 'order_items.item_id', '=', 'courses.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('item_id', DB::raw('count(*) as orders, sum(orders.amount) as earnings, courses.title as name, courses.slug'))
            ->groupBy('item_id')
            ->get();

        return \DataTables::of($course_orders)
            ->addIndexColumn()
            ->addColumn('course', function ($q) {
                $course_name = $q->title;
                $course_slug = $q->slug;
                $link = "<a href='".route('courses.show', [$course_slug])."' target='_blank'>".$course_name."</a>";
                return $link;
            })
            ->rawColumns(['course'])
            ->make();
    }

    public function getBundleData(Request $request)
    {
        $bundles = Bundle::ofTeacher()->has('students','>',0)->withCount('students')->pluck('id');

        $bundle_orders = OrderItem::whereHas('order',function ($q){
            $q->where('status','=',1);
        })->where('item_type','=',Bundle::class)
            ->whereIn('item_id',$bundles)
            ->join('bundles', 'order_items.item_id', '=', 'bundles.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('item_id', DB::raw('count(*) as orders, sum(orders.amount) as earnings, bundles.title as name, bundles.slug'))
            ->groupBy('item_id')
            ->get();


        return \DataTables::of($bundle_orders)
            ->addIndexColumn()
            ->addColumn('bundle', function ($q) {
                $bundle_name = $q->title;
                $bundle_slug = $q->slug;
                $link = "<a href='".route('bundles.show', [$bundle_slug])."' target='_blank'>".$bundle_name."</a>";
                return $link;
            })
            ->addColumn('students',function ($q){
                return $q->students_count;
            })
            ->rawColumns(['bundle'])
            ->make();
    }

    public function getStudentsData(Request $request){
        $courses = Course::ofTeacher()->has('students','>',0)->withCount('students')->get();

        return \DataTables::of($courses)
            ->addIndexColumn()
            ->addColumn('completed', function ($q) {
                $count = 0;
                if(count($q->students) > 0){
                    foreach ($q->students as $student){
                        $completed_lessons =  $student->chapters()->where('course_id', $q->id)->get()->pluck('model_id')->toArray();
                        if (count($completed_lessons) > 0) {
                            $progress = intval(count($completed_lessons) / $q->courseTimeline->count() * 100);
                            if($progress == 100){
                                $count++;
                            }
                        }
                    }
                }
                return $count;

            })
            ->make();
    }
}
