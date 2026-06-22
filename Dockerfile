# Server image: reads snapshot.json (read-only mount), renders SVG cards.
# No secrets, no git, no GitHub access. Multi-stage build with a fat jar.

FROM maven:3.9-eclipse-temurin-21 AS build
WORKDIR /workspace
COPY pom.xml .
COPY ghstats-common/pom.xml ghstats-common/pom.xml
COPY ghstats-collector/pom.xml ghstats-collector/pom.xml
COPY ghstats-server/pom.xml ghstats-server/pom.xml
RUN mvn -q -B -pl ghstats-server -am dependency:go-offline
COPY ghstats-common/src ghstats-common/src
COPY ghstats-server/src ghstats-server/src
RUN mvn -q -B -pl ghstats-server -am -DskipTests package

FROM eclipse-temurin:21-jre AS runtime
RUN groupadd --system ghstats && useradd --system --gid ghstats --home /app ghstats
WORKDIR /app
COPY --from=build /workspace/ghstats-server/target/ghstats-server.jar app.jar
USER ghstats
EXPOSE 14330
ENV JAVA_OPTS="-XX:MaxRAMPercentage=75.0"
ENTRYPOINT ["sh", "-c", "exec java $JAVA_OPTS -jar app.jar"]
