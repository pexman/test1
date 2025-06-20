//+------------------------------------------------------------------+
//|                                           Spike Predictor.mq5   |
//|                         Copyright 2025                          |
//+------------------------------------------------------------------+
#property copyright "© 2025"
#property version   "1.0"
#property indicator_chart_window
#property indicator_plots 0

//--- Input parameters
input group "=== Configuración de Predicción ==="
input bool   InpEnablePredictions = true;          // Habilitar predicciones
input int    InpTrendPeriod = 7;                   // Período para análisis de tendencia
input double InpMinConfidenceLevel = 60.0;         // Nivel mínimo de confianza para mostrar predicción (%)
input int    InpMaxPredictions = 5;                // Máximo de predicciones simultáneas
input int    InpPredictionIntervalMin = 15;        // Intervalo mínimo entre predicciones (minutos)

input group "=== Configuración MACD ==="
input int    InpMACDFastEMA = 12;                  // MACD Fast EMA
input int    InpMACDSlowEMA = 26;                  // MACD Slow EMA
input int    InpMACDSignalSMA = 9;                 // MACD Signal SMA

input group "=== Configuración de Volatilidad ==="
input int    InpVolatilityPeriod = 10;             // Período para cálculo de volatilidad
input double InpVolatilityMultiplier = 1.5;        // Multiplicador de volatilidad para spikes
input int    InpSpikeThreshold = 150;              // Umbral mínimo para detectar spike (puntos)

input group "=== Configuración Visual ==="
input color  InpPredictionColor = clrRed;          // Color de líneas de predicción
input color  InpSuccessColor = clrLime;            // Color para predicciones exitosas
input color  InpFailedColor = clrGray;             // Color para predicciones fallidas
input bool   InpShowStatistics = true;             // Mostrar estadísticas en panel
input string InpFontName = "Arial";                // Fuente para etiquetas
input int    InpFontSize = 10;                     // Tamaño de fuente

//--- Global variables
int macdHandle = INVALID_HANDLE;
int rsiHandle = INVALID_HANDLE;
int volatilityPeriod = 10;
datetime lastPredictionTime = 0;

//--- Prediction structure
struct SPrediction {
    datetime timeExpected;      // Tiempo esperado del spike
    double priceExpected;       // Precio esperado del spike
    datetime predictionTime;    // Cuándo se hizo la predicción
    double confidence;          // Nivel de confianza (0-100%)
    bool validated;             // Si ya fue validada
    bool successful;            // Si fue exitosa
    int id;                     // ID único para objetos gráficos
};

SPrediction predictions[];
int nextPredictionId = 0;
string predictionPrefix = "SpikePred_";

//--- Statistics
int totalPredictions = 0;
int successfulPredictions = 0;
double accuracyRate = 0.0;

//--- Panel objects
string PREFIX = "SPIKE_PREDICTOR_";
string PANEL_NAME = PREFIX + "PANEL";
string STATS_TITLE = PREFIX + "STATS_TITLE";
string ACCURACY_LABEL = PREFIX + "ACCURACY";
string TOTAL_LABEL = PREFIX + "TOTAL";
string NEXT_PREDICTION_LABEL = PREFIX + "NEXT_PRED";

//+------------------------------------------------------------------+
//| Custom indicator initialization function                         |
//+------------------------------------------------------------------+
int OnInit() {
    // Initialize handles
    macdHandle = iMACD(_Symbol, PERIOD_M15, InpMACDFastEMA, InpMACDSlowEMA, InpMACDSignalSMA, PRICE_CLOSE);
    if(macdHandle == INVALID_HANDLE) {
        Print("Error creando handle MACD");
        return INIT_FAILED;
    }
    
    rsiHandle = iRSI(_Symbol, PERIOD_CURRENT, 14, PRICE_CLOSE);
    if(rsiHandle == INVALID_HANDLE) {
        Print("Error creando handle RSI");
        return INIT_FAILED;
    }
    
    // Initialize arrays
    ArrayResize(predictions, 0);
    
    // Initialize statistics
    totalPredictions = 0;
    successfulPredictions = 0;
    accuracyRate = 0.0;
    
    // Create visual panel
    if(InpShowStatistics) {
        CreateStatisticsPanel();
    }
    
    Print("Spike Predictor Indicator inicializado");
    return INIT_SUCCEEDED;
}

//+------------------------------------------------------------------+
//| Custom indicator deinitialization function                      |
//+------------------------------------------------------------------+
void OnDeinit(const int reason) {
    // Release handles
    if(macdHandle != INVALID_HANDLE) IndicatorRelease(macdHandle);
    if(rsiHandle != INVALID_HANDLE) IndicatorRelease(rsiHandle);
    
    // Delete all prediction objects and panel
    ObjectsDeleteAll(0, PREFIX);
    
    // Delete prediction lines
    for(int i = 0; i < 1000; i++) { // Clean up to ID 1000
        ObjectDelete(0, predictionPrefix + "Time_" + IntegerToString(i));
        ObjectDelete(0, predictionPrefix + "Label_" + IntegerToString(i));
        ObjectDelete(0, predictionPrefix + "Cross_" + IntegerToString(i));
    }
    
    Print("Spike Predictor Indicator desinicializado");
}

//+------------------------------------------------------------------+
//| Custom indicator iteration function                              |
//+------------------------------------------------------------------+
int OnCalculate(const int rates_total,
                const int prev_calculated,
                const datetime &time[],
                const double &open[],
                const double &high[],
                const double &low[],
                const double &close[],
                const long &tick_volume[],
                const long &volume[],
                const int &spread[]) {
    
    // Only process on new bar
    static datetime lastBarTime = 0;
    datetime currentBarTime = time[rates_total - 1];
    
    if(currentBarTime <= lastBarTime) {
        return rates_total;
    }
    lastBarTime = currentBarTime;
    
    // Main prediction logic
    if(InpEnablePredictions) {
        PredictFutureSpike();
        UpdateExistingPredictions();
        ValidatePredictions();
    }
    
    // Update statistics panel
    if(InpShowStatistics) {
        UpdateStatisticsPanel();
    }
    
    return rates_total;
}

//+------------------------------------------------------------------+
//| Analyze trend for the specified period                          |
//+------------------------------------------------------------------+
double AnalyzeTrend(int period) {
    double closes[];
    ArrayResize(closes, period);
    ArraySetAsSeries(closes, true);
    
    if(CopyClose(_Symbol, PERIOD_M15, 0, period, closes) != period) {
        Print("Error copiando precios de cierre para análisis de tendencia");
        return 0.0;
    }
    
    // Calculate general direction
    double firstAvg = (closes[period-1] + closes[period-2] + closes[period-3]) / 3.0;
    double lastAvg = (closes[0] + closes[1] + closes[2]) / 3.0;
    
    // Calculate slope
    double slope = (lastAvg - firstAvg) / period;
    
    // Normalize between -1 and 1
    double maxMove = 100 * _Point; // 100 points as expected maximum movement
    double normalizedSlope = slope / maxMove;
    
    // Limit between -1 and 1
    normalizedSlope = MathMax(-1.0, MathMin(1.0, normalizedSlope));
    
    return normalizedSlope;
}

//+------------------------------------------------------------------+
//| Get current MACD values                                         |
//+------------------------------------------------------------------+
bool GetMACDValues(double &macdLine, double &signalLine) {
    if(macdHandle == INVALID_HANDLE) return false;
    
    double macdBuffer[];
    double signalBuffer[];
    
    ArrayResize(macdBuffer, 3);
    ArrayResize(signalBuffer, 3);
    ArraySetAsSeries(macdBuffer, true);
    ArraySetAsSeries(signalBuffer, true);
    
    if(CopyBuffer(macdHandle, 0, 0, 3, macdBuffer) <= 0 ||
       CopyBuffer(macdHandle, 1, 0, 3, signalBuffer) <= 0) {
        return false;
    }
    
    macdLine = macdBuffer[0];
    signalLine = signalBuffer[0];
    
    return true;
}

//+------------------------------------------------------------------+
//| Calculate current volatility ratio                              |
//+------------------------------------------------------------------+
double CalculateVolatilityRatio() {
    int requiredBars = InpVolatilityPeriod + 1;
    if(Bars(_Symbol, PERIOD_CURRENT) < requiredBars) return 1.0;
    
    MqlRates rates[];
    if(CopyRates(_Symbol, PERIOD_CURRENT, 0, requiredBars, rates) != requiredBars) return 1.0;
    
    // Calculate average size of previous candles
    double avgSize = 0;
    for(int i = 1; i < requiredBars; i++) {
        avgSize += MathAbs(rates[i].high - rates[i].low);
    }
    avgSize /= (requiredBars - 1);
    
    if(avgSize == 0) return 1.0;
    
    // Calculate current candle size
    double currentSize = MathAbs(rates[0].high - rates[0].low);
    
    return currentSize / avgSize;
}

//+------------------------------------------------------------------+
//| Calculate market momentum factor                                 |
//+------------------------------------------------------------------+
double CalculateMarketMomentum() {
    // Get RSI for momentum calculation
    double rsiValue[];
    ArrayResize(rsiValue, 1);
    ArraySetAsSeries(rsiValue, true);
    
    if(CopyBuffer(rsiHandle, 0, 0, 1, rsiValue) <= 0) {
        return 0.5; // Neutral momentum
    }
    
    // Convert RSI to momentum factor (0-1)
    double momentum = 0.0;
    
    // Extreme RSI levels suggest higher probability of reversal (spike)
    if(rsiValue[0] <= 30.0 || rsiValue[0] >= 70.0) {
        momentum = 0.8; // High momentum for spike
    } else if(rsiValue[0] <= 40.0 || rsiValue[0] >= 60.0) {
        momentum = 0.6; // Medium momentum
    } else {
        momentum = 0.3; // Low momentum in neutral zone
    }
    
    return momentum;
}

//+------------------------------------------------------------------+
//| Predict future spike with multifactorial analysis              |
//+------------------------------------------------------------------+
void PredictFutureSpike() {
    datetime currentTime = TimeCurrent();
    
    // Only predict every X minutes to avoid spam
    if(currentTime - lastPredictionTime < InpPredictionIntervalMin * 60) return;
    
    // 1. Analyze trend
    double trendSlope = AnalyzeTrend(InpTrendPeriod);
    
    // 2. Get MACD for confirmation
    double macdMain, macdSignal;
    bool macdValid = GetMACDValues(macdMain, macdSignal);
    if(!macdValid) return;
    
    // 3. Calculate volatility
    double volatilityRatio = CalculateVolatilityRatio();
    
    // 4. Calculate market momentum
    double momentum = CalculateMarketMomentum();
    
    // 5. Evaluate conditions for prediction
    bool trendCondition = MathAbs(trendSlope) > 0.3; // Significant slope (either direction)
    bool macdCondition = (macdMain < 0 && macdMain > macdSignal) || 
                        (macdMain > 0 && macdMain < macdSignal); // MACD crossing
    bool volatilityCondition = volatilityRatio > 1.2 || volatilityRatio < 0.8; // Unusual volatility
    bool momentumCondition = momentum > 0.6; // High momentum
    
    // Calculate confidence based on factors
    double confidence = 0.0;
    
    if(trendCondition) confidence += 25.0;
    if(macdCondition) confidence += 25.0;
    if(volatilityCondition) confidence += 20.0;
    if(momentumCondition) confidence += 30.0;
    
    // Bonus for extreme conditions
    if(MathAbs(trendSlope) > 0.7) confidence += 10.0;
    if(volatilityRatio > 1.5) confidence += 10.0;
    
    // Only create prediction if confidence is sufficient
    if(confidence >= InpMinConfidenceLevel) {
        // Estimate when spike will occur (1-5 candles ahead based on confidence)
        int candles = (int)MathRound(6.0 - (confidence/100.0) * 5.0);
        candles = MathMax(1, MathMin(5, candles));
        
        // Get future time based on current timeframe
        ENUM_TIMEFRAMES currentTF = Period();
        datetime predictedTime = iTime(_Symbol, currentTF, 0) + PeriodSeconds(currentTF) * candles;
        
        // Get current price
        double currentPrice = SymbolInfoDouble(_Symbol, SYMBOL_BID);
        
        // Calculate expected spike size
        double recentVolatility = volatilityRatio * 100.0 * _Point; // Base volatility
        double spikeSize = recentVolatility * InpVolatilityMultiplier * (confidence/100.0);
        
        // Minimum spike size
        spikeSize = MathMax(spikeSize, InpSpikeThreshold * _Point);
        
        // Direction based on trend and MACD
        double direction = 1.0; // Default up
        if(trendSlope < 0 && macdMain < 0) direction = -1.0; // Down
        
        double predictedPrice = currentPrice + (spikeSize * direction);
        
        // Create prediction
        int idx = ArraySize(predictions);
        ArrayResize(predictions, idx + 1);
        
        predictions[idx].timeExpected = predictedTime;
        predictions[idx].priceExpected = predictedPrice;
        predictions[idx].predictionTime = currentTime;
        predictions[idx].confidence = confidence;
        predictions[idx].validated = false;
        predictions[idx].successful = false;
        predictions[idx].id = nextPredictionId++;
        
        // Limit number of predictions
        if(ArraySize(predictions) > InpMaxPredictions) {
            // Remove oldest prediction (and its objects)
            DeletePredictionObjects(predictions[0].id);
            
            for(int i = 0; i < ArraySize(predictions) - 1; i++) {
                predictions[i] = predictions[i + 1];
            }
            ArrayResize(predictions, ArraySize(predictions) - 1);
        }
        
        // Draw prediction
        DrawPredictionLines(predictedTime, predictedPrice, confidence, predictions[idx].id);
        
        // Update statistics
        totalPredictions++;
        
        // Update last prediction time
        lastPredictionTime = currentTime;
        
        Print("Nueva predicción de spike: Tiempo=", TimeToString(predictedTime), 
              ", Precio=", DoubleToString(predictedPrice, _Digits), 
              ", Confianza=", DoubleToString(confidence, 1), "%",
              ", Dirección=", direction > 0 ? "UP" : "DOWN");
    }
}

//+------------------------------------------------------------------+
//| Draw prediction lines on chart                                  |
//+------------------------------------------------------------------+
void DrawPredictionLines(datetime predictedTime, double predictedPrice, double confidence, int id) {
    string timeLineName = predictionPrefix + "Time_" + IntegerToString(id);
    string labelName = predictionPrefix + "Label_" + IntegerToString(id);
    string crossName = predictionPrefix + "Cross_" + IntegerToString(id);
    
    color lineColor = InpPredictionColor;
    
    // Vertical time line
    if(ObjectCreate(0, timeLineName, OBJ_VLINE, 0, predictedTime, 0)) {
        ObjectSetInteger(0, timeLineName, OBJPROP_COLOR, lineColor);
        ObjectSetInteger(0, timeLineName, OBJPROP_STYLE, STYLE_DASH);
        ObjectSetInteger(0, timeLineName, OBJPROP_WIDTH, 2);
        ObjectSetString(0, timeLineName, OBJPROP_TOOLTIP, 
                       "Predicción de Spike\nTiempo: " + TimeToString(predictedTime, TIME_DATE|TIME_MINUTES) + 
                       "\nConfianza: " + DoubleToString(confidence, 1) + "%");
        ObjectSetInteger(0, timeLineName, OBJPROP_BACK, false);
        ObjectSetInteger(0, timeLineName, OBJPROP_SELECTABLE, false);
        ObjectSetInteger(0, timeLineName, OBJPROP_HIDDEN, true);
    }
    
    // Cross at predicted point
    if(ObjectCreate(0, crossName, OBJ_ARROW, 0, predictedTime, predictedPrice)) {
        ObjectSetInteger(0, crossName, OBJPROP_ARROWCODE, 167); // Cross symbol
        ObjectSetInteger(0, crossName, OBJPROP_COLOR, lineColor);
        ObjectSetInteger(0, crossName, OBJPROP_WIDTH, 3);
        ObjectSetString(0, crossName, OBJPROP_TOOLTIP, 
                       "Punto de impacto esperado\nPrecio: " + DoubleToString(predictedPrice, _Digits) +
                       "\nConfianza: " + DoubleToString(confidence, 1) + "%");
        ObjectSetInteger(0, crossName, OBJPROP_BACK, false);
        ObjectSetInteger(0, crossName, OBJPROP_SELECTABLE, false);
        ObjectSetInteger(0, crossName, OBJPROP_HIDDEN, true);
    }
    
    // Text label
    if(ObjectCreate(0, labelName, OBJ_TEXT, 0, predictedTime, predictedPrice + 15 * _Point)) {
        string labelText = "SPIKE " + DoubleToString(confidence, 0) + "%";
        ObjectSetString(0, labelName, OBJPROP_TEXT, labelText);
        ObjectSetString(0, labelName, OBJPROP_FONT, InpFontName);
        ObjectSetInteger(0, labelName, OBJPROP_FONTSIZE, InpFontSize);
        ObjectSetInteger(0, labelName, OBJPROP_COLOR, lineColor);
        ObjectSetInteger(0, labelName, OBJPROP_BACK, false);
        ObjectSetInteger(0, labelName, OBJPROP_SELECTABLE, false);
        ObjectSetInteger(0, labelName, OBJPROP_HIDDEN, true);
    }
    
    ChartRedraw();
}

//+------------------------------------------------------------------+
//| Update existing predictions                                      |
//+------------------------------------------------------------------+
void UpdateExistingPredictions() {
    datetime currentTime = TimeCurrent();
    
    for(int i = 0; i < ArraySize(predictions); i++) {
        if(!predictions[i].validated && currentTime < predictions[i].timeExpected) {
            // Get time remaining
            double timeRemaining = (predictions[i].timeExpected - currentTime) / 
                                  (double)PeriodSeconds(PERIOD_M15);
            
            // If getting close to prediction time (within 2 periods), adjust confidence
            if(timeRemaining < 2.0) {
                double adjustedConfidence = predictions[i].confidence;
                
                // Get current market conditions
                double currentVolatility = CalculateVolatilityRatio();
                double currentMomentum = CalculateMarketMomentum();
                
                // Adjust confidence based on current conditions
                if(currentVolatility > 1.3) adjustedConfidence += 5.0;
                if(currentMomentum > 0.7) adjustedConfidence += 5.0;
                
                // Keep in valid range
                adjustedConfidence = MathMax(0.0, MathMin(100.0, adjustedConfidence));
                
                // Update if significant change
                if(MathAbs(adjustedConfidence - predictions[i].confidence) > 5.0) {
                    predictions[i].confidence = adjustedConfidence;
                    
                    // Redraw with new confidence
                    DrawPredictionLines(predictions[i].timeExpected, 
                                       predictions[i].priceExpected,
                                       adjustedConfidence, 
                                       predictions[i].id);
                }
            }
        }
    }
}

//+------------------------------------------------------------------+
//| Validate predictions against actual results                     |
//+------------------------------------------------------------------+
void ValidatePredictions() {
    datetime currentTime = TimeCurrent();
    
    for(int i = 0; i < ArraySize(predictions); i++) {
        if(!predictions[i].validated && 
           currentTime > predictions[i].timeExpected + PeriodSeconds(Period()) * 2) {
            
            // Time to validate this prediction
            bool spikeDetected = false;
            
            // Check for spike around predicted time
            datetime startTime = predictions[i].timeExpected - PeriodSeconds(Period());
            datetime endTime = predictions[i].timeExpected + PeriodSeconds(Period()) * 2;
            
            // Get bars in the validation window
            MqlRates rates[];
            int barsToCheck = 5; // Check 5 bars around prediction
            ArrayResize(rates, barsToCheck);
            ArraySetAsSeries(rates, true);
            
            if(CopyRates(_Symbol, Period(), predictions[i].timeExpected, barsToCheck, rates) > 0) {
                for(int j = 0; j < ArraySize(rates); j++) {
                    // Calculate body size and total range
                    double bodySize = MathAbs(rates[j].close - rates[j].open) / _Point;
                    double totalRange = (rates[j].high - rates[j].low) / _Point;
                    
                    // Check if there was a significant spike
                    if(bodySize >= InpSpikeThreshold || totalRange >= InpSpikeThreshold * 1.5) {
                        // Check if price was close to predicted price
                        double priceDifference = MathAbs(rates[j].close - predictions[i].priceExpected) / _Point;
                        
                        if(priceDifference <= InpSpikeThreshold) { // Within threshold
                            spikeDetected = true;
                            break;
                        }
                    }
                }
            }
            
            // Mark as validated
            predictions[i].validated = true;
            predictions[i].successful = spikeDetected;
            
            // Update statistics
            if(spikeDetected) {
                successfulPredictions++;
                
                // Change color to success
                ChangeObjectColor(predictions[i].id, InpSuccessColor);
                
                Print("✅ Predicción EXITOSA - ID: ", predictions[i].id, 
                      ", Confianza: ", DoubleToString(predictions[i].confidence, 1), "%");
            } else {
                // Change color to failed
                ChangeObjectColor(predictions[i].id, InpFailedColor);
                
                Print("❌ Predicción FALLIDA - ID: ", predictions[i].id, 
                      ", Confianza: ", DoubleToString(predictions[i].confidence, 1), "%");
            }
            
            // Calculate accuracy rate
            accuracyRate = (double)successfulPredictions / totalPredictions * 100.0;
        }
    }
}

//+------------------------------------------------------------------+
//| Change color of prediction objects                              |
//+------------------------------------------------------------------+
void ChangeObjectColor(int id, color newColor) {
    string timeLineName = predictionPrefix + "Time_" + IntegerToString(id);
    string labelName = predictionPrefix + "Label_" + IntegerToString(id);
    string crossName = predictionPrefix + "Cross_" + IntegerToString(id);
    
    ObjectSetInteger(0, timeLineName, OBJPROP_COLOR, newColor);
    ObjectSetInteger(0, labelName, OBJPROP_COLOR, newColor);
    ObjectSetInteger(0, crossName, OBJPROP_COLOR, newColor);
}

//+------------------------------------------------------------------+
//| Delete prediction objects                                        |
//+------------------------------------------------------------------+
void DeletePredictionObjects(int id) {
    string timeLineName = predictionPrefix + "Time_" + IntegerToString(id);
    string labelName = predictionPrefix + "Label_" + IntegerToString(id);
    string crossName = predictionPrefix + "Cross_" + IntegerToString(id);
    
    ObjectDelete(0, timeLineName);
    ObjectDelete(0, labelName);
    ObjectDelete(0, crossName);
}

//+------------------------------------------------------------------+
//| Create statistics panel                                          |
//+------------------------------------------------------------------+
void CreateStatisticsPanel() {
    int panelX = 20;
    int panelY = 80;
    int panelWidth = 250;
    int panelHeight = 120;
    
    // Main panel
    ObjectCreate(0, PANEL_NAME, OBJ_RECTANGLE_LABEL, 0, 0, 0);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_XDISTANCE, panelX);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_YDISTANCE, panelY);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_XSIZE, panelWidth);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_YSIZE, panelHeight);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_BGCOLOR, C'25,30,40');
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_BORDER_TYPE, BORDER_FLAT);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_COLOR, C'70,80,90');
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_WIDTH, 1);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_BACK, false);
    ObjectSetInteger(0, PANEL_NAME, OBJPROP_HIDDEN, true);
    
    // Title
    ObjectCreate(0, STATS_TITLE, OBJ_LABEL, 0, 0, 0);
    ObjectSetInteger(0, STATS_TITLE, OBJPROP_XDISTANCE, panelX + 10);
    ObjectSetInteger(0, STATS_TITLE, OBJPROP_YDISTANCE, panelY + 10);
    ObjectSetString(0, STATS_TITLE, OBJPROP_TEXT, "SPIKE PREDICTOR STATISTICS");
    ObjectSetString(0, STATS_TITLE, OBJPROP_FONT, InpFontName);
    ObjectSetInteger(0, STATS_TITLE, OBJPROP_FONTSIZE, InpFontSize + 1);
    ObjectSetInteger(0, STATS_TITLE, OBJPROP_COLOR, clrWhite);
    ObjectSetInteger(0, STATS_TITLE, OBJPROP_BACK, false);
    ObjectSetInteger(0, STATS_TITLE, OBJPROP_HIDDEN, true);
    
    // Accuracy label
    ObjectCreate(0, ACCURACY_LABEL, OBJ_LABEL, 0, 0, 0);
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_XDISTANCE, panelX + 10);
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_YDISTANCE, panelY + 35);
    ObjectSetString(0, ACCURACY_LABEL, OBJPROP_TEXT, "Precisión: 0.0%");
    ObjectSetString(0, ACCURACY_LABEL, OBJPROP_FONT, InpFontName);
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_FONTSIZE, InpFontSize);
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_COLOR, clrLightBlue);
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_BACK, false);
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_HIDDEN, true);
    
    // Total predictions label
    ObjectCreate(0, TOTAL_LABEL, OBJ_LABEL, 0, 0, 0);
    ObjectSetInteger(0, TOTAL_LABEL, OBJPROP_XDISTANCE, panelX + 10);
    ObjectSetInteger(0, TOTAL_LABEL, OBJPROP_YDISTANCE, panelY + 55);
    ObjectSetString(0, TOTAL_LABEL, OBJPROP_TEXT, "Total: 0 | Exitosas: 0");
    ObjectSetString(0, TOTAL_LABEL, OBJPROP_FONT, InpFontName);
    ObjectSetInteger(0, TOTAL_LABEL, OBJPROP_FONTSIZE, InpFontSize);
    ObjectSetInteger(0, TOTAL_LABEL, OBJPROP_COLOR, clrYellow);
    ObjectSetInteger(0, TOTAL_LABEL, OBJPROP_BACK, false);
    ObjectSetInteger(0, TOTAL_LABEL, OBJPROP_HIDDEN, true);
    
    // Next prediction info
    ObjectCreate(0, NEXT_PREDICTION_LABEL, OBJ_LABEL, 0, 0, 0);
    ObjectSetInteger(0, NEXT_PREDICTION_LABEL, OBJPROP_XDISTANCE, panelX + 10);
    ObjectSetInteger(0, NEXT_PREDICTION_LABEL, OBJPROP_YDISTANCE, panelY + 75);
    ObjectSetString(0, NEXT_PREDICTION_LABEL, OBJPROP_TEXT, "Próxima predicción: Calculando...");
    ObjectSetString(0, NEXT_PREDICTION_LABEL, OBJPROP_FONT, InpFontName);
    ObjectSetInteger(0, NEXT_PREDICTION_LABEL, OBJPROP_FONTSIZE, InpFontSize);
    ObjectSetInteger(0, NEXT_PREDICTION_LABEL, OBJPROP_COLOR, clrLightGreen);
    ObjectSetInteger(0, NEXT_PREDICTION_LABEL, OBJPROP_BACK, false);
    ObjectSetInteger(0, NEXT_PREDICTION_LABEL, OBJPROP_HIDDEN, true);
}

//+------------------------------------------------------------------+
//| Update statistics panel                                          |
//+------------------------------------------------------------------+
void UpdateStatisticsPanel() {
    if(!InpShowStatistics) return;
    
    // Update accuracy
    string accuracyText = "Precisión: " + DoubleToString(accuracyRate, 1) + "%";
    ObjectSetString(0, ACCURACY_LABEL, OBJPROP_TEXT, accuracyText);
    
    // Update totals
    string totalText = "Total: " + IntegerToString(totalPredictions) + 
                      " | Exitosas: " + IntegerToString(successfulPredictions);
    ObjectSetString(0, TOTAL_LABEL, OBJPROP_TEXT, totalText);
    
    // Update next prediction info
    string nextPredText = "Próxima predicción: ";
    
    // Find closest future prediction
    datetime currentTime = TimeCurrent();
    datetime closestTime = 0;
    double closestConfidence = 0;
    
    for(int i = 0; i < ArraySize(predictions); i++) {
        if(!predictions[i].validated && predictions[i].timeExpected > currentTime) {
            if(closestTime == 0 || predictions[i].timeExpected < closestTime) {
                closestTime = predictions[i].timeExpected;
                closestConfidence = predictions[i].confidence;
            }
        }
    }
    
    if(closestTime > 0) {
        int minutesTo = (int)(closestTime - currentTime) / 60;
        nextPredText += "En " + IntegerToString(minutesTo) + " min (" + 
                       DoubleToString(closestConfidence, 0) + "%)";
    } else {
        int minutesUntilNext = InpPredictionIntervalMin - 
                              (int)(currentTime - lastPredictionTime) / 60;
        if(minutesUntilNext > 0) {
            nextPredText += "En " + IntegerToString(minutesUntilNext) + " min máx.";
        } else {
            nextPredText += "Analizando condiciones...";
        }
    }
    
    ObjectSetString(0, NEXT_PREDICTION_LABEL, OBJPROP_TEXT, nextPredText);
    
    // Color coding for accuracy
    color accuracyColor = clrRed;
    if(accuracyRate > 70.0) accuracyColor = clrLime;
    else if(accuracyRate > 50.0) accuracyColor = clrYellow;
    else if(accuracyRate > 30.0) accuracyColor = clrOrange;
    
    ObjectSetInteger(0, ACCURACY_LABEL, OBJPROP_COLOR, accuracyColor);
}

//+------------------------------------------------------------------+