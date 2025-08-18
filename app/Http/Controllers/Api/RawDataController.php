<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawDataRecord;
use App\Models\RawUploadRecord;
use App\Models\ActivityLog;
use App\Jobs\ProcessFileUpload;
use App\Jobs\ProcessFileDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RawDataController extends Controller
{
    /**
     * 上传文件并处理粗数据
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,csv|max:10240', // 10MB
            'country' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $filename = time() . '_' . $originalFilename;
            $filePath = 'raw_uploads/' . $filename;

            // 存储文件
            Storage::disk('public')->put($filePath, file_get_contents($file->getRealPath()));

            // 创建上传记录
            $uploadRecord = RawUploadRecord::create([
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'user_id' => auth()->id(),
                'status' => 'pending',
                'country' => $request->country,
                'industry' => $request->industry,
                'remarks' => $request->remarks,
            ]);

            // 分发队列任务
            ProcessFileUpload::dispatch($uploadRecord, 'raw');

            // 记录活动日志
            ActivityLog::log(
                'upload',
                "API上传粗数据文件：{$originalFilename}",
                [
                    'upload_record_id' => $uploadRecord->id,
                    'filename' => $originalFilename,
                    'data_type' => 'raw',
                ],
                $uploadRecord
            );

            return response()->json([
                'success' => true,
                'message' => '粗数据文件上传成功，正在后台处理中',
                'data' => [
                    'upload_record_id' => $uploadRecord->id,
                    'filename' => $originalFilename,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取粗数据记录列表
     */
    public function index(Request $request)
    {
        $query = RawDataRecord::query();

        // 应用过滤器
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('country')) {
            $query->whereHas('uploadRecord', function ($q) use ($request) {
                $q->where('country', 'like', '%' . $request->country . '%');
            });
        }

        if ($request->filled('industry')) {
            $query->whereHas('uploadRecord', function ($q) use ($request) {
                $q->where('industry', 'like', '%' . $request->industry . '%');
            });
        }

        // 分页
        $perPage = $request->get('per_page', 15);
        $records = $query->with('uploadRecord')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * 获取单个粗数据记录
     */
    public function show($id)
    {
        $record = RawDataRecord::with('uploadRecord')->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record
        ]);
    }

    /**
     * 更新粗数据记录
     */
    public function update(Request $request, $id)
    {
        $record = RawDataRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20|unique:raw_data_records,phone,' . $id,
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $record->update($request->only(['phone', 'data']));

        // 记录活动日志
        ActivityLog::log(
            'update',
            "更新粗数据记录：{$record->phone}",
            [
                'record_id' => $record->id,
                'phone' => $record->phone,
            ],
            $record
        );

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $record
        ]);
    }

    /**
     * 删除粗数据记录
     */
    public function destroy($id)
    {
        $record = RawDataRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        $phone = $record->phone;
        $record->delete();

        // 记录活动日志
        ActivityLog::log(
            'delete',
            "删除粗数据记录：{$phone}",
            [
                'record_id' => $id,
                'phone' => $phone,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 下载粗数据
     */
    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,xlsx',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = RawDataRecord::query();

            // 应用过滤器
            if ($request->filled('filters.phone')) {
                $query->where('phone', 'like', '%' . $request->filters['phone'] . '%');
            }

            if ($request->filled('filters.country')) {
                $query->whereHas('uploadRecord', function ($q) use ($request) {
                    $q->where('country', 'like', '%' . $request->filters['country'] . '%');
                });
            }

            if ($request->filled('filters.industry')) {
                $query->whereHas('uploadRecord', function ($q) use ($request) {
                    $q->where('industry', 'like', '%' . $request->filters['industry'] . '%');
                });
            }

            $records = $query->with('uploadRecord')->get();

            // 创建下载记录
            $downloadRecord = \App\Models\DownloadRecord::create([
                'filename' => 'raw_data_' . time() . '.' . $request->format,
                'format' => $request->format,
                'total_count' => $records->count(),
                'user_id' => auth()->id(),
                'status' => 'pending',
            ]);

            // 分发下载任务
            ProcessFileDownload::dispatch($downloadRecord, $records, 'raw');

            // 记录活动日志
            ActivityLog::log(
                'download',
                "请求下载粗数据：{$downloadRecord->filename}",
                [
                    'download_record_id' => $downloadRecord->id,
                    'format' => $request->format,
                    'count' => $records->count(),
                ],
                $downloadRecord
            );

            return response()->json([
                'success' => true,
                'message' => '下载任务已创建，请稍后查看下载记录',
                'data' => [
                    'download_record_id' => $downloadRecord->id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '下载失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量删除粗数据
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:raw_data_records,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $records = RawDataRecord::whereIn('id', $request->ids)->get();
        $count = $records->count();

        $records->each(function ($record) {
            $record->delete();
        });

        // 记录活动日志
        ActivityLog::log(
            'batch_delete',
            "批量删除粗数据记录：{$count}条",
            [
                'count' => $count,
                'ids' => $request->ids,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "成功删除 {$count} 条记录"
        ]);
    }
}
