namespace php zemanta
# 
# EXAMPLE JAVA NAMESPACE
# Not used in this example
# 
# namespace java org.robwilkerson.nlp.zemanta

struct Freebase {
  1: string topic,
  2: string id,
  3: double confidence,
  4: list<string> categories
}

struct Dmoz {
  1: string topic,
  2: double confidence,
  3: list<string> categories
}

struct ZemantaAnalysis {
  1: list<Freebase> freebase,
  2: list<Dmoz> dmoz
}

service zemanta {
  ZemantaAnalysis analyze(1:string content)
}
