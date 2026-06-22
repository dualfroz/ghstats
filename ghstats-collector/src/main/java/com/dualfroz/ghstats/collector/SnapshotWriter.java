package com.dualfroz.ghstats.collector;

import com.dualfroz.ghstats.common.SnapshotMapper;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.io.IOException;
import java.nio.file.AtomicMoveNotSupportedException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class SnapshotWriter {

    private static final Logger log = LoggerFactory.getLogger(SnapshotWriter.class);

    private final CollectorProperties properties;
    private final ObjectMapper mapper = SnapshotMapper.create();

    public SnapshotWriter(CollectorProperties properties) {
        this.properties = properties;
    }

    public void write(Snapshot snapshot) throws IOException {
        Path target = Path.of(properties.getSnapshotPath());
        Files.createDirectories(target.getParent());
        Path temp = target.resolveSibling(target.getFileName() + ".tmp");

        byte[] payload = mapper.writerWithDefaultPrettyPrinter().writeValueAsBytes(snapshot);
        Files.write(temp, payload);

        try {
            Files.move(temp, target, StandardCopyOption.REPLACE_EXISTING, StandardCopyOption.ATOMIC_MOVE);
        } catch (AtomicMoveNotSupportedException noAtomic) {
            Files.move(temp, target, StandardCopyOption.REPLACE_EXISTING);
        }
        log.info("Wrote snapshot to {} ({} bytes)", target, payload.length);
    }
}
