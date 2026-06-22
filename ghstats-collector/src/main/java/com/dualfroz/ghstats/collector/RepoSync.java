package com.dualfroz.ghstats.collector;

import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Duration;
import java.util.List;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class RepoSync {

    private static final Logger log = LoggerFactory.getLogger(RepoSync.class);

    private final ProcessRunner processRunner;
    private final CollectorProperties properties;

    public RepoSync(ProcessRunner processRunner, CollectorProperties properties) {
        this.processRunner = processRunner;
        this.properties = properties;
    }

    public Path ensureCheckout(RemoteRepo repo) {
        Path target = Path.of(properties.getReposRoot(), repo.owner(), repo.repo());
        if (Files.isDirectory(target.resolve(".git"))) {
            pull(repo, target);
            return target;
        }
        return clone(repo, target);
    }

    private void pull(RemoteRepo repo, Path target) {
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getGitBin(), "-C", target.toString(), "pull", "--ff-only", "--quiet"),
                null, Duration.ofSeconds(properties.getGitTimeoutSeconds()));
        if (!result.ok()) {
            log.warn("Pull failed for {} (keeping existing checkout): {}",
                    repo.nameWithOwner(), result.stderr().trim());
        }
    }

    private Path clone(RemoteRepo repo, Path target) {
        try {
            Files.createDirectories(target.getParent());
        } catch (Exception createFailure) {
            log.warn("Cannot create parent dir for {}: {}", repo.nameWithOwner(), createFailure.getMessage());
            return null;
        }
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getGhBin(), "repo", "clone", repo.nameWithOwner(), target.toString()),
                null, Duration.ofSeconds(properties.getCloneTimeoutSeconds()));
        if (!result.ok()) {
            log.warn("Clone failed for {}: {}", repo.nameWithOwner(), result.stderr().trim());
            return null;
        }
        return target;
    }
}
