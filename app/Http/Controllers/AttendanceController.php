<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Http\Request;
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
            'image'          => 'required',
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


            $similarity = $result['FaceMatches'][0]['Similarity'];
            $employee_id = $result['FaceMatches'][0]['Face']['ExternalImageId'];

            $guard = Employee::where('employee_id', $employee_id)->first();
            return $guard;
        } catch (Exception $ex) {

            return response()->json([
                'Message'      => 'Person not Found',
                'status'       => 'error',
                'exception' => $ex
            ], 500);
        }

        return response()->json([
            'similarity'  => $similarity,
            'guard'       => $guard,
            'status'      => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
        # code...
    }
}
