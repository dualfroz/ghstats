package com.dualfroz.ghstats.collector;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.boot.CommandLineRunner;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.context.properties.EnableConfigurationProperties;

@SpringBootApplication
@EnableConfigurationProperties(CollectorProperties.class)
public class CollectorApplication implements CommandLineRunner {

    private static final Logger log = LoggerFactory.getLogger(CollectorApplication.class);

    private final CollectorRunner runner;

    public CollectorApplication(CollectorRunner runner) {
        this.runner = runner;
    }

    public static void main(String[] args) {
        SpringApplication application = new SpringApplication(CollectorApplication.class);
        application.setWebApplicationType(org.springframework.boot.WebApplicationType.NONE);
        System.exit(SpringApplication.exit(application.run(args)));
    }

    @Override
    public void run(String... args) throws Exception {
        long startedAt = System.currentTimeMillis();
        log.info("ghstats collector starting");
        runner.run();
        log.info("ghstats collector finished in {} ms", System.currentTimeMillis() - startedAt);
    }
}
