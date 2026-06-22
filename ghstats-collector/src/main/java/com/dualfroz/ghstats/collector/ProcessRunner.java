package com.dualfroz.ghstats.collector;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Path;
import java.time.Duration;
import java.util.List;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;
import org.springframework.stereotype.Component;

@Component
public class ProcessRunner {

    public record ProcessResult(int exitCode, String stdout, String stderr, boolean timedOut) {
        public boolean ok() {
            return !timedOut && exitCode == 0;
        }
    }

    public ProcessResult run(List<String> command, Path workingDir, Duration timeout) {
        ProcessBuilder builder = new ProcessBuilder(command);
        if (workingDir != null) {
            builder.directory(workingDir.toFile());
        }
        builder.redirectErrorStream(false);

        Process process;
        try {
            process = builder.start();
        } catch (IOException startFailure) {
            return new ProcessResult(-1, "", "failed to start: " + startFailure.getMessage(), false);
        }

        AtomicReference<String> stderr = new AtomicReference<>("");
        Thread stderrDrain = drainAsync(process.getErrorStream(), stderr);
        String stdout = readFully(process.getInputStream());

        boolean finished;
        try {
            finished = process.waitFor(timeout.toMillis(), TimeUnit.MILLISECONDS);
        } catch (InterruptedException interrupted) {
            Thread.currentThread().interrupt();
            process.destroyForcibly();
            return new ProcessResult(-1, stdout, "interrupted", true);
        }

        if (!finished) {
            process.destroyForcibly();
            joinQuietly(stderrDrain);
            return new ProcessResult(-1, stdout, "timed out after " + timeout.toSeconds() + "s", true);
        }

        joinQuietly(stderrDrain);
        return new ProcessResult(process.exitValue(), stdout, stderr.get(), false);
    }

    private static Thread drainAsync(InputStream stream, AtomicReference<String> sink) {
        Thread thread = new Thread(() -> sink.set(readFully(stream)));
        thread.setDaemon(true);
        thread.start();
        return thread;
    }

    private static String readFully(InputStream stream) {
        try (stream) {
            ByteArrayOutputStream sink = new ByteArrayOutputStream();
            byte[] chunk = new byte[8192];
            int read;
            while ((read = stream.read(chunk)) != -1) {
                sink.write(chunk, 0, read);
            }
            return sink.toString(StandardCharsets.UTF_8);
        } catch (IOException readFailure) {
            return "";
        }
    }

    private static void joinQuietly(Thread thread) {
        try {
            thread.join(2000);
        } catch (InterruptedException interrupted) {
            Thread.currentThread().interrupt();
        }
    }
}
