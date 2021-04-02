// VARIABLES
var gulp = require('gulp'),
    replace = require('gulp-string-replace'),
    inject = require('gulp-inject-string');

var swim_client_cron_source = 'swim-client-cron.sh',
    swim_client_cron_target = 'build/';

// TASKS
gulp.task('build-cron', async function () {
    gulp.src(swim_client_cron_source)
        .pipe(replace(/#.*[\n\r]/g, '', {skipBinary: true}))
        .pipe(replace(/[\n\r]/g, '', {skipBinary: true}))
        .pipe(inject.append(' >/dev/null 2>&1'))
        .pipe(gulp.dest(swim_client_cron_target));
});

// DEFAULT - build
gulp.task('default', gulp.series('build-cron'));

// WATCH
gulp.task('watch', async function () {
    gulp.watch(swim_client_cron_source, gulp.series('build-cron'));
});
