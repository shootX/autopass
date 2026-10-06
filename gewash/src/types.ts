    export type PackageData = {
    id: number;
    plate: string;
    car_id: number;
    model: string;
    washes: number | "infinity";
    period: number;
    startDate: Date;
    autoRenewal: boolean;
  };