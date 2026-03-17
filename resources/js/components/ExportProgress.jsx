import { useState, useEffect, useCallback } from 'react';

interface ExportProgressProps {
    exportId: string;
    onComplete?: (downloadUrl: string) => void;
    onError?: (error: string) => void;
    pollingInterval?: number;
}

interface ProgressData {
    progress: number;
    total: number;
    status: 'processing' | 'completed' | 'failed' | 'not_found';
    file_path?: string;
    updated_at?: string;
}

export default function ExportProgress({
    exportId,
    onComplete,
    onError,
    pollingInterval = 1000,
}: ExportProgressProps) {
    const [progress, setProgress] = useState<ProgressData>({
        progress: 0,
        total: 0,
        status: 'processing',
    });
    const [error, setError] = useState<string | null>(null);
    const [downloadUrl, setDownloadUrl] = useState<string | null>(null);

    const fetchProgress = useCallback(async () => {
        try {
            const response = await fetch(`/api/exports/${exportId}/progress`);
            
            if (!response.ok) {
                if (response.status === 404) {
                    setError('Export not found');
                    onError?.('Export not found');
                    return;
                }
                throw new Error('Failed to fetch progress');
            }

            const data: ProgressData = await response.json();
            setProgress(data);

            if (data.status === 'completed' && data.file_path) {
                const signedUrlResponse = await fetch(
                    `/api/exports/${exportId}/download?signed_url=true`
                );
                
                if (signedUrlResponse.ok) {
                    const url = signedUrlResponse.url || `/api/exports/${exportId}/download`;
                    setDownloadUrl(url);
                    onComplete?.(url);
                }
            } else if (data.status === 'failed') {
                setError('Export failed');
                onError?.('Export failed');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
            onError?.(err instanceof Error ? err.message : 'Unknown error');
        }
    }, [exportId, onComplete, onError]);

    useEffect(() => {
        fetchProgress();
        
        if (progress.status === 'processing') {
            const interval = setInterval(fetchProgress, pollingInterval);
            return () => clearInterval(interval);
        }
    }, [fetchProgress, pollingInterval, progress.status]);

    const getStatusColor = () => {
        switch (progress.status) {
            case 'completed':
                return 'bg-green-500';
            case 'failed':
                return 'bg-red-500';
            default:
                return 'bg-blue-500';
        }
    };

    const getStatusText = () => {
        switch (progress.status) {
            case 'completed':
                return 'Export Complete!';
            case 'failed':
                return 'Export Failed';
            case 'processing':
                return `Processing... ${progress.progress}%`;
            default:
                return 'Initializing...';
        }
    };

    return (
        <div className="w-full max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
            <div className="mb-4">
                <h3 className="text-lg font-semibold text-gray-800">
                    Export Progress
                </h3>
                <p className="text-sm text-gray-500">
                    Export ID: {exportId}
                </p>
            </div>

            {error && (
                <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                    <p className="text-sm text-red-600">{error}</p>
                </div>
            )}

            <div className="mb-2">
                <div className="flex justify-between text-sm text-gray-600 mb-1">
                    <span>{getStatusText()}</span>
                    <span>
                        {progress.total > 0 
                            ? `${Math.round((progress.progress / 100) * progress.total).toLocaleString()} / ${progress.total.toLocaleString()}`
                            : 'Calculating...'
                        }
                    </span>
                </div>
                
                <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div
                        className={`h-full transition-all duration-300 ease-out ${getStatusColor()}`}
                        style={{ width: `${progress.progress}%` }}
                    />
                </div>
            </div>

            {progress.status === 'processing' && (
                <div className="mt-4 flex items-center justify-center">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500" />
                </div>
            )}

            {downloadUrl && progress.status === 'completed' && (
                <a
                    href={downloadUrl}
                    className="mt-4 block w-full py-2 px-4 bg-green-500 hover:bg-green-600 text-white text-center font-medium rounded-md transition-colors"
                    download
                >
                    Download Export
                </a>
            )}

            {progress.updated_at && (
                <p className="mt-3 text-xs text-gray-400">
                    Last updated: {new Date(progress.updated_at).toLocaleString()}
                </p>
            )}
        </div>
    );
}
