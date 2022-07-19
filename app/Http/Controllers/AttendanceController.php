<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Aws\Rekognition\RekognitionClient;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $attendances = Attendance::with("employee")
            ->whereBetween('check_in', [$request->from, $request->to])
            ->when($request->employee_id, function ($q) use ($request) {
                return $q->where('employee_id', $request->employee_id);
            })
            ->get()->map(function ($item) {
                $item->employee = $item->employee->makeHidden(['attendances']);
                return $item;
            });

        $attendances = $attendances->map(function ($attendance) {
            $attendance->name = $attendance->employee->name;
            $attendance->employee_id = $attendance->employee->employee_id;
            return $attendance;
        });

        $attendances = $attendances->groupBy(function ($item) {
            return $item->check_in->format('Y-m-d');
        });

        $data = collect();

        foreach ($attendances as $key => $attendance) {
            $data->push(
                [
                    'date' => $key,
                    'attendances' => $attendance
                ]
            );
        }

        return response()->json([
            'data'      => $data
        ], 200);
    }

    public function check(Request $request)
    {
        $request->validate([
            'image'       => 'required',
        ]);

        $image = fopen($request->file('image')->getPathName(), 'r');
        $bytes = fread($image, $request->file('image')->getSize());

        try {
            $client = new RekognitionClient(config('aws.recognition'));

            $result = $client->searchFacesByImage([
                'CollectionId' => "employee.attendance", // REQUIRED
                '`FaceMatchThreshold`' => 90.00,
                'Image' => [ // REQUIRED
                    'Bytes' => $bytes,
                ],
                'MaxFaces' => 1,
            ]);

            $employee_id = $result['FaceMatches'][0]['Face']['ExternalImageId'];

            $employee = Employee::where('employee_id', $employee_id)->first();
            if (!$employee)
                return response()->json([
                    'Message'      => 'Person not Found',
                    'status'       => 'error'
                ], 404);

            return response()->json([
                'data'      => [$employee]
            ], 200);
        } catch (Exception $ex) {

            return response()->json([
                'Message'      => 'Person not Found',
                'status'       => 'error',
                'exception' => $ex
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        DB::beginTransaction();
        try {

            $employee = Employee::find($request->employee_id);

            $message = "Employee already completed work";
            if ($employee->status == "pending") {
                $employee->attendances()->create([
                    "check_in" => Carbon::now()
                ]);
                $message = "Employee Check In to work";
            } else if ($employee->status == "on-duty") {
                $attendance = $employee->attendances()->whereDate('check_in', Carbon::today())->first();
                $attendance->update([
                    "check_out" => Carbon::now()
                ]);
                $message = "Employee Check Out of work";
            }

            DB::commit();
            return response()->json([
                'message' => $message,
                'code'    => 200,
                'status'  => 'success'
            ], 200);
        } catch (Exception $ex) {

            DB::rollback();
            return response()->json([
                'code'    => 500,
                'message' => "Something Went Wrong..Please Try Again",
                'error'   => $ex->getMessage()
            ], 500);
        }
    }

    public function delete(Attendance $attendance)
    {

        DB::beginTransaction();
        try {
            $attendance->delete();
            DB::commit();
            return response()->json([
                'message' => "Attendance Deleted",
                'code'    => 200,
                'status'  => 'success'
            ], 200);
        } catch (Exception $ex) {

            DB::rollback();
            return response()->json([
                'code'    => 500,
                'message' => "Something Went Wrong..Please Try Again",
                'error'   => $ex->getMessage()
            ], 500);
        }
    }
}
