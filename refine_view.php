<?php
$file = 'resources/views/objectives/index.blade.php';
$content = file_get_contents($file);

// 1. Move the approval block outside @can
$approvalBlockPattern = '/@if\(\$mainObj->status === \'submitted\' && \(auth\(\)->user\(\)->isPlanReportHeadOffice\(\) \|\| auth\(\)->user\(\)->isSystemAdmin\(\)\)\).*?@endif/s';

if (preg_match($approvalBlockPattern, $content, $matches)) {
    $block = $matches[0];
    
    // Update the check to include isHeadOffice()
    $newBlock = str_replace(
        'isPlanReportHeadOffice() || auth()->user()->isSystemAdmin()',
        'isPlanReportHeadOffice() || auth()->user()->isHeadOffice() || auth()->user()->isSystemAdmin()',
        $block
    );
    
    // Remove it from current location
    $content = str_replace($block, '', $content);
    
    // Inject it after @endcan but before the closing </div>
    $content = str_replace(
        "@endcan\n                        </div>",
        "@endcan\n\n                            $newBlock\n                        </div>",
        $content
    );
    
    file_put_contents($file, $content);
    echo "Refined index view successfully.";
} else {
    echo "Could not find approval block.";
}
