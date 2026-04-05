# Retry mechanism plan

I need to create a comprehensive Artisan console command that analyzes expired or failed images and attempts to automatically diagnose why they failed.

In the first step, check whether the image was downloaded at all — i.e. the `image_file` field is populated — and then retrieve file information from disk to validate whether the file is OK. If it's not, delete it, set `image_file` to null, and re-trigger the download and variant generation process.

Next, examine `variant_files`. If it's null, trigger a new generation.

If `variant_files` contains entries, verify that all variants have been generated and exist on disk. If any are missing or corrupted, delete them from disk, set `variant_files` to null, and re-dispatch the job.

The job should be dispatched synchronously (e.g. dispatchSync) so that any exceptions are caught and printed to the console output with a clear description of the problem.
