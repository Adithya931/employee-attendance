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
        return Attendance::whereBetween('check_in', [$request->from, $request->to])
            ->when($request->employee_id, function ($q) use ($request) {
                return $q->where('employee_id', $request->employee_id);
            })
            ->get();
    }

    public function check(Request $request)
    {
        $request->validate([
            'image'       => 'required',
            // 'employee_id' => ['required', 'exists:employees,id'],
            'mark' => ['required', 'boolean'],
        ]);

        $image = fopen($request->file('image')->getPathName(), 'r');
        $bytes = fread($image, $request->file('image')->getSize());

        DB::beginTransaction();
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


            $similarity = $result['FaceMatches'][0]['Similarity'];
            $employee_id = $result['FaceMatches'][0]['Face']['ExternalImageId'];

            $employee = Employee::where('employee_id', $employee_id)->first();

            if ($request->mark) {
                $data = $this->store($employee);
                DB::commit();

                if ($employee->status == "pending") {
                    return response()->json([
                        'message'  => "Employee Check In to work",
                        'employee'       => $employee,
                        'status'      => 'success'
                    ], 200);
                } else if ($employee->status == "on-duty") {
                    return response()->json([
                        'message'  => "Employee Check Out of work",
                        'employee'       => $employee,
                        'status'      => 'success'
                    ], 200);
                }

                return response()->json([
                    'message'  => "Employee work already completed",
                    'employee'       => $employee,
                    'status'      => 'success'
                ], 200);
            }

            return response()->json([
                'similarity'  => $similarity,
                'employee'       => $employee,
                'status'      => 'success'
            ], 200);
        } catch (Exception $ex) {

            DB::rollback();
            return response()->json([
                'Message'      => 'Person not Found',
                'status'       => 'error',
                'exception' => $ex
            ], 500);
        }
    }

    public function store(Employee $employee)
    {

        if ($employee->status == "pending") {
            $employee->attendances()->create([
                "check_in" => Carbon::now()
            ]);
        } else if ($employee->status == "on-duty") {
            $attendance = $employee->attendances->where('check_in', Carbon::today())->first();
            $attendance->update([
                "check_out" => Carbon::now()
            ]);
        }

        return $employee;
    }
}
